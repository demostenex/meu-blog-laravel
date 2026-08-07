# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Desenvolvimento local (servidor + queue + pail + Vite simultâneos)
composer dev

# Rodar todos os testes
composer test

# Rodar um teste específico (por classe ou método)
php artisan test --filter GenerateEnglishVersionJobTest
php artisan test --filter "edit_page_dispatches_job"

# Linting / code style
./vendor/bin/pint

# Produção (Docker)
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

O ambiente local roda na porta definida em `NGINX_PORT` no `.env` (padrão **8004** se omitida) via `docker-compose.prod.yml`. A porta é uma variável de ambiente (`${NGINX_PORT:-8004}:80`), não um valor fixo no compose — evita conflito de merge entre ambientes que rodam em portas diferentes.

## Arquitetura Geral

Blog pessoal de autor único. **Laravel 13 + Livewire Volt + Tailwind CSS + PostgreSQL + Redis.**

Todas as views administrativas e públicas são **Livewire Volt single-file components** em `resources/views/livewire/` — PHP e Blade no mesmo arquivo. Não há controllers Blade tradicionais; os poucos controllers em `app/Http/Controllers/` atendem apenas `FeedController` (RSS) e `SitemapController`.

### Autor único

`User::resolveRouteBinding()` sempre retorna `User::first()`, e o registro só é possível quando o banco está vazio. Nunca há mais de um usuário.

### Infraestrutura Docker

`docker-compose.prod.yml` sobe cinco serviços: `app` (PHP-FPM), `worker` (`queue:work`), `nginx`, `redis` e `db` (PostgreSQL 15). Vendor e node_modules são volumes nomeados para não serem sobrescritos pelo bind mount do código.

`Dockerfile.prod` é local e **não versionado** (`.gitignore`); `Dockerfile.prod.example` é o template rastreado no git — qualquer ajuste de infra (extensões PHP, `memory_limit`, etc.) precisa ser replicado nos dois, ou o `Dockerfile.prod` local fica defasado e só se descobre o problema quando a imagem é reconstruída (`docker compose ... up -d --build`) meses depois. `memory_limit` do PHP é 512M (não o padrão 128M) — necessário pra decodificar respostas HTTP grandes em base64, como áudio de TTS embutido em JSON.

`app` e `worker` compartilham a mesma imagem (`meublog-app:latest`); reconstruir só um deles ainda deixa o outro na imagem antiga até `--build` rodar de novo. Depois de reconstruir `app`/`worker` sem reconstruir `nginx`, é preciso `docker compose restart nginx` — ele resolve o hostname `app` uma vez no boot e mantém o IP em cache, então continua apontando pro container antigo (removido) e devolve 502.

---

## Arquitetura de IA (multi-provider)

O sistema resolve o serviço de IA correto por usuário em runtime, sem acoplamento a um provider fixo.

```
Contracts/AiService (interface)
    └── AbstractAiService (translateHtml com DOMDocument + placeholders de URL)
            └── GeminiService (construtor: apiKey, model, persona)
                    ↑ instanciado por
AiServiceFactory (singleton no container)
    ├── for(User $user) → busca UserAiProvider default → chama make()
    └── make(UserAiProvider, capability) → retorna AiService concreto
```

**Modelos de dados:**
- `UserAiProvider` — provider (`gemini`/`openai`/`anthropic`), `api_key` (encrypted), flag `is_default`
- `UserAiModel` — model name + `capability` (`text`, `image` ou `audio`) + `is_default` por provider
- `UserAiPersona` — texto da persona, `ai_name`, `ai_photo`, `accent_color`; ligada ao provider via `persona_id`

**Regra:** qualquer código que precise do serviço de IA recebe `AiServiceFactory` por injeção e chama `$factory->for($user)`. Nunca instancie `GeminiService` diretamente fora da factory.

**`ImagenService`** e **`TtsService`** são casos especiais: chamam a Gemini API diretamente (não passam pela interface `AiService`) porque geração de imagem e de áudio têm payload/resposta diferentes de texto (`responseModalities: ['IMAGE']`/`['AUDIO']` em vez de texto puro). Resolvem o modelo via `$factory->imageModelFor($provider)`/`$factory->audioModelFor($provider)`.

### Tradução de posts

`GenerateEnglishVersionJob` (queued) usa `AiServiceFactory::for($user)` para traduzir título (texto plano) e conteúdo (HTML). O método `translateHtml()` na `AbstractAiService` usa DOMDocument para substituir todos os `href`/`src`/`data-src` por placeholders antes de enviar ao modelo e os restaura depois.

Flag `content_en_locked = true` no post → job respeita a tradução manual e apenas marca `status = done`.  
Campo `content_en_error` persiste a mensagem do último erro de tradução.

### Memória contextual do Kikito

`GeminiService::buildCommentPrompt()` injeta os últimos 5 posts publicados no prompt do comentário, permitindo referências cruzadas entre artigos.

### Narração em áudio (TTS)

Botão manual no editor (`posts/edit.blade.php`) dispara `GeneratePostAudioJob` (queued), que chama `TtsService::generateAudio()` — API de TTS do Gemini (`generateContent` com `generationConfig.responseModalities: ['AUDIO']` e `speechConfig.voiceConfig.prebuiltVoiceConfig.voiceName`). Vozes disponíveis em `TtsService::VOICES` (subconjunto curado, com descritor de timbre); escolha salva por post em `audio_voice`, com fallback pra `Kore` se inválida.

A API retorna PCM cru em base64 (16-bit, mono, 24kHz, sem cabeçalho de arquivo) — `AudioService::storePcmAsWav()` envelopa isso num WAV e salva via streaming (arquivo temporário → disco), nunca concatenando o áudio inteiro numa string só (estoura memória fácil pra posts longos). Texto enviado é limitado a 8000 chars (`Str::limit`) pra manter a narração numa duração/tamanho de payload razoável.

**Sem geração automática nem revalidação**: é sempre o autor clicando "gerar"/"regerar"; se o post for editado depois, o áudio antigo continua servido (só um aviso visual comparando `updated_at` com `audio_generated_at`).

Campos no `Post`: `audio_path`, `audio_status` (`pending`/`done`/`error`), `audio_voice`, `audio_error`, `audio_generated_at`. Mesmo padrão de polling de `content_en_status` (`#[Poll(750, 'isAudioPending')]`).

`GeneratePostAudioJob` usa `WithoutOverlapping($this->postId)` — sem isso, dois disparos pro mesmo post (ex: autor clica duas vezes, ou dois usuários testando ao mesmo tempo) fazem um job apagar do disco o áudio que o outro acabou de gerar com sucesso (a limpeza de "áudio antigo antes de regenerar" enxerga o `audio_path` do job concorrente como obsoleto).

A API pode legitimamente estourar o timeout HTTP (300s) na primeira tentativa mesmo com o modelo mais rápido (`gemini-2.5-flash-preview-tts`) — `tries=2` no job cobre isso; o timeout do job (`$timeout = 360`) precisa ficar acima do timeout do `Http::timeout()`, senão o worker mata o processo antes do cliente HTTP conseguir lançar uma exceção capturável. Prefira `gemini-2.5-flash-preview-tts` a `gemini-2.5-pro-preview-tts` — o "pro" já se mostrou instável (retorna `finishReason: "OTHER"` sem áudio, ou trava sem responder).

---

## Analytics Soberano (sem Google)

Pipeline de rastreamento sem dependência de terceiros:

1. **`TrackPostView` middleware** — registra view de post via `Redis::incr("post:views:{$id}")` (operação O(1), sem gravar no DB)
2. **`TrackPageView` middleware** — para GETs anônimos não-bot, despacha `RecordPageViewJob` (async) que grava em `page_views` com IP anonimizado (SHA-256 + app key como salt)
3. **`FlushViewsBuffer` command** — drena os contadores Redis para `posts.views_count` no banco. É chamado automaticamente no evento `Login` via `AppServiceProvider`.

`PageView` não tem `updated_at` (só `created_at`) e não usa timestamps automáticos do Eloquent.

O dashboard exibe views 7d/30d, visitantes únicos (distinct `ip_hash`), top páginas, top referrers e breakdown por dispositivo.

---

## Cache HTTP

`spatie/laravel-responsecache` com backend Redis (1 hora de TTL por padrão).

**`PublicOnlyCacheProfile`** — só cacheia GET anônimos; ignora requests com header `X-Livewire` (polling do Livewire) e qualquer usuário autenticado.

**Invalidação:** `PostObserver::updated()` e `deleted()` chamam `ResponseCache::clear()` (limpeza total). Rotas autenticadas usam o alias `doNotCacheResponse`.

Parâmetros UTM, gclid e fbclid são ignorados na geração da cache key (configurado em `config/responsecache.php`).

---

## Storage

Dois discos configuráveis via `IMAGE_DISK` no `.env`:
- `public` — armazenamento local em `storage/app/public/`
- `r2` — Cloudflare R2 (S3-compatible), com prefixo opcional configurável via `R2_ROOT` (útil pra separar ambientes no mesmo bucket)

O helper global `image_url(?string $path): string` em `app/helpers.php` resolve a URL correta para o disco ativo. **Sempre use este helper** em vez de `Storage::url()` ou `asset()` para imagens — vale também pra áudio e documentos, não só imagens, apesar do nome.

Diretórios de mídia: `covers/`, `profiles/`, `ai-avatars/`, `post-images/`, `post-videos/`, `post-audio/` (narrações TTS), `documents/` (anexos de download).

`SyncMediaToR2` command: migra assets locais para R2 sem regenerar nada (`--dry-run` e `--force` disponíveis).

---

## Documentos para download

`Document` model — `title`, `path`, `original_filename`, `mime_type`, `size`, `post_id` (nullable, `belongsTo(Post)`; `Post::documents()` é o `hasMany` inverso). Upload restrito a PDF/Office/ZIP até 10MB, validado no admin (`documents/index.blade.php`, rota `/documentos`).

Exibido em dois lugares: seção "Documentos para download" na página do post (só quando `post_id` aponta pra ele) e página pública `/biblioteca` listando todo o acervo, paginada. Sem contador de download — link direto via `image_url($document->path)`.

---

## Testes

Padrão de teste: **`DatabaseTransactions`** (rollback após cada teste), nunca `RefreshDatabase`.

Sintaxe PHPUnit 12: atributo `#[Test]` em vez de prefixo `test_`.

Para testar código que usa IA via `AiServiceFactory::for()` (tradução, comentário do Kikito), **mocke `AiServiceFactory`**, não `GeminiService`:

```php
$mock = \Mockery::mock(AiService::class);
$this->mock(AiServiceFactory::class)
     ->shouldReceive('for')->andReturn($mock);
```

Para `ImagenService`/`TtsService` (que bypassam a factory), **mocke o serviço diretamente** (`$this->mock(TtsService::class)->shouldReceive('generateAudio')->andReturn(...)`) nos testes de job/Livewire; para testar o serviço em si (payload, voz enviada, fallback de modelo), use `Http::fake([...])` e `Http::assertSent(fn ($request) => ...)` — ver `tests/Unit/Services/TtsServiceTest.php` e `GeminiServiceTranslateTest.php` para os dois estilos.

Testes de Livewire usam `Volt::actingAs($user)->test('posts.edit', ['post' => $post])`.

O `phpunit.xml` define `QUEUE_CONNECTION=sync` e `CACHE_STORE=array` para testes — Redis não é usado em testes.

---

## Comandos Artisan relevantes

| Comando | Descrição |
|---|---|
| `app:flush-views-buffer` | Drena Redis → `posts.views_count` (disparado no Login) |
| `app:optimize-images` | Recomprime imagens locais e atualiza refs no banco |
| `app:kikito-report` | Relatório do bot Kikito |
| `media:sync-to-r2` | Copia assets locais para Cloudflare R2 |
| `backup:run` | Dump PostgreSQL + zip de imagens → e-mail |
