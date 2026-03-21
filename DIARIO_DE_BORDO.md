# Diário de Bordo - Blog demostenesalbert.com.br

Este documento registra as evoluções técnicas, desafios superados e decisões de arquitetura tomadas durante o desenvolvimento do blog.

### 🎨 Design e Experiência de Leitura (UI/UX)
- **Sumário Flutuante (Sticky TOC):** Implementado sumário lateral que acompanha a leitura, com scroll interno e design refinado (borda lateral reativa).
- **Refinamento de Tipografia:** 
    - Redução do tamanho do título principal (H1) para 50% do original, buscando elegância.
    - Estilização direta dos elementos gerados pelo Trix (H1, H2, H3 internos) para evitar tamanhos exagerados e melhorar o ritmo visual.
    - Implementação de layout em Grid para garantir a estabilidade do sumário e do conteúdo.

### 📱 Social Media e SEO (A Iniciar)
- **Open Graph (OG Tags):** Implementação de meta tags dinâmicas para que o WhatsApp e redes sociais exibam a imagem da capa, título e descrição corretamente ao compartilhar links.

---

## 📅 Sessão: 20 de Março de 2026

### 🛠️ Infraestrutura e Docker (Foco: Produção)
- **Segurança de Configuração:** Criado `Dockerfile.prod.example` e adicionado o `Dockerfile.prod` real ao `.gitignore` para proteger as configurações específicas do servidor.
- **Usuário e Permissões:** Ajustado o `Dockerfile.prod` para rodar com o usuário `demostenes` (UID 1000), garantindo compatibilidade com as permissões do host.
- **Otimização de Build:** Criado o arquivo `.dockerignore` para evitar que lixo de desenvolvimento (`vendor/` local, `node_modules/`, caches) seja levado para a imagem de produção.
- **Correção de Uploads:** Adicionado `client_max_body_size 100M` ao `nginx.conf` para permitir o upload de fotos de perfil e imagens pesadas nos posts.

### 🚀 Desafios Superados (Bug Fixes)
1. **Erro de Banco no Build:**
   - **Problema:** O comando `composer install --no-dev --optimize-autoloader` falhava no Docker porque o arquivo de rotas tentava contar usuários no banco (que ainda não existia na fase de build).
   - **Solução:** Removida a lógica de banco do `routes/auth.php`. A trava de "Autor Único" foi movida para o método `mount()` do componente Livewire de Registro.

2. **Erro "Class Laravel\Pail\PailServiceProvider not found":**
   - **Problema:** Cache residual do ambiente de desenvolvimento tentava carregar pacotes de dev em produção.
   - **Solução 1:** Adicionado `laravel/pail` à seção `dont-discover` do `composer.json`.
   - **Solução 2:** Adicionado comando `RUN rm -rf bootstrap/cache/*.php` no `Dockerfile.prod` antes da instalação do composer.

3. **Mixed Content e SSL (HTTPS):**
   - **Problema:** O site em HTTPS tentava fazer uploads via HTTP (XMLHttpRequest bloqueado), causando falha nos uploads do Livewire.
   - **Solução:** Configurado `AppServiceProvider` para forçar HTTPS em produção e `bootstrap/app.php` para confiar em todos os proxies (`trustProxies('*')`).

### 📝 Documentação e Git
- **Manual de Deploy:** O `README.md` foi totalmente atualizado com um passo a passo detalhado para deploy via Docker e Instalação Pura.
- **Status do Projeto:** `GEMINI.md` atualizado com o progresso das etapas (Disqus integrado e CRUDs finalizados).
- **Fluxo de Trabalho:** Implementado uso de branchs de feature (`feat/deploy-optimization`) e merge seguro na `master` após validação em ambiente produtivo simulado.

### 🎨 Design e Experiência de Leitura (UI/UX)
- **Sumário Flutuante (Sticky TOC):** Implementado sumário lateral que acompanha a leitura, com scroll interno e design refinado (borda lateral reativa).
- **Refinamento de Tipografia:** 
    - Redução do tamanho do título principal (H1) para 50% do original, buscando elegância.
    - Estilização direta dos elementos gerados pelo Trix (H1, H2, H3 internos) para evitar tamanhos exagerados e melhorar o ritmo visual.
    - Implementação de layout em Grid para garantir a estabilidade do sumário e do conteúdo.

### 📱 Social Media e SEO (A Iniciar)
- **Open Graph (OG Tags):** Implementação de meta tags dinâmicas para que o WhatsApp e redes sociais exibam a imagem da capa, título e descrição corretamente ao compartilhar links.

---

### ✅ Status Atual
- **Ambiente de Produção:** Operacional na porta 8003 (local) / 443 (servidor).
- **Funcionalidades:** Postagens, Autenticação, Comentários (Disqus) e Uploads (HTTPS) funcionando.
- **Segurança:** Trava de registro de usuário único validada em runtime.

### 🎨 Design e Experiência de Leitura (UI/UX)
- **Sumário Flutuante (Sticky TOC):** Implementado sumário lateral que acompanha a leitura, com scroll interno e design refinado (borda lateral reativa).
- **Refinamento de Tipografia:** 
    - Redução do tamanho do título principal (H1) para 50% do original, buscando elegância.
    - Estilização direta dos elementos gerados pelo Trix (H1, H2, H3 internos) para evitar tamanhos exagerados e melhorar o ritmo visual.
    - Implementação de layout em Grid para garantir a estabilidade do sumário e do conteúdo.

### 📱 Social Media e SEO (A Iniciar)
- **Open Graph (OG Tags):** Implementação de meta tags dinâmicas para que o WhatsApp e redes sociais exibam a imagem da capa, título e descrição corretamente ao compartilhar links.

---
*Assinado: Gemini CLI & Demóstenes*

---

## 📅 Sessão: 21 de Março de 2026

### 🤖 Integração com Gemini AI — Comentarista Sarcástico

Implementação de um bot de IA que lê cada artigo e publica um comentário automático com personalidade configurável pelo administrador.

#### Arquitetura da Solução
- **Estratégia de armazenamento:** Comentários salvos na tabela `ai_comments` (em vez de um campo no post), permitindo histórico completo e regeneração sem perda do anterior.
- **Configuração por usuário:** Todos os parâmetros da IA ficam na tabela `users`, já que o blog é de autor único — sem necessidade de tabela separada.
- **Service isolado:** Criado `app/Services/GeminiService.php` que encapsula toda a comunicação com a API REST do Gemini.

#### O que foi criado
1. **Migration `add_gemini_fields_to_users_table`** — campos `gemini_api_key`, `gemini_model`, `gemini_ai_name`, `gemini_ai_photo`, `gemini_persona`.
2. **Migration `create_ai_comments_table`** — tabela com `post_id` (FK), `content` (longText), `model`.
3. **Model `AiComment`** — com relação `belongsTo(Post)`.
4. **Model `Post`** — relações `aiComments()` e `latestAiComment()` adicionadas.
5. **`GeminiService`** — chama a API `v1beta/models/{model}:generateContent` com a persona definida pelo admin.
6. **Profile** — nova seção "IA Comentarista" com: seletor de modelo, campo de API key, upload de avatar (10 MB), nome do bot e textarea de persona.
7. **Post edit** — bloco com botão "✨ Gerar / 🔄 Regenerar comentário" com spinner, preview do comentário atual e feedback de erro inline.
8. **Post show (público)** — exibe o comentário da IA com avatar, nome e modelo usado, acima do Disqus, num card roxo destacado.

#### Modelos disponíveis na lista
- `gemini-2.5-pro` (mais inteligente — ID correto, sem sufixo de data)
- `gemini-2.0-flash` (rápido, padrão)
- `gemini-2.0-flash-lite` (econômico)
- `gemini-1.5-pro` / `gemini-1.5-flash`

#### Desafios Superados
- **404 no modelo `gemini-2.5-pro-exp-03-25`:** O ID experimental com sufixo de data não existe na API v1beta. Corrigido para `gemini-2.5-pro` (ID estável).
- **Tabela não existia em produção:** As migrations rodaram no ambiente de dev (Sail), mas o banco de produção precisou de `docker compose -f docker-compose.prod.yml exec app php artisan migrate --force` separado.

#### Outros
- Criado `.github/copilot-instructions.md` → `../GEMINI.md` para que o Copilot CLI leia as instruções do projeto automaticamente.

---
*Assinado: Copilot CLI & Demóstenes*

---

## 📅 Sessão: 21 de Março de 2026 (tarde)

### 🐛 Fix: Menu duplo no Dashboard + ComponentNotFoundException

O dashboard usava `<x-app-layout>` diretamente no template, mas o Livewire/Volt já aplica automaticamente o layout `layouts.app` (configurado em `config/livewire.php`). Resultado: duas navbars empilhadas.

**Solução:** arquivo movido de `resources/views/dashboard.blade.php` → `resources/views/livewire/dashboard.blade.php` (caminho que o Volt procura) e `<x-app-layout>` substituído por `<div>` + `<x-slot name="header">`, seguindo o padrão dos demais componentes admin.

---

### 🔐 Rascunho e Pré-visualização de Artigos

Implementado sistema completo de rascunho com pré-visualização antes de publicar.

#### Decisão de arquitetura
Campo `published_at` (timestamp nullable) na tabela `posts`:
- `null` → rascunho (invisível para visitantes)
- preenchido → publicado (exibido na homepage pela data de publicação)

Posts já existentes migrados automaticamente com `published_at = created_at`.

#### O que foi criado / alterado
1. **Migration `add_published_at_to_posts_table`** — nova coluna + backfill dos posts existentes.
2. **Model `Post`** — campo adicionado ao `#[Fillable]`, cast para `datetime`, scope `scopePublished()`, método `isPublished()`.
3. **Criar artigo (`posts/create`)** — dois botões: **Publicar** e **💾 Salvar Rascunho**. Ao salvar rascunho, redireciona direto para a edição.
4. **Editar artigo (`posts/edit`)** — badge de status no header (✅ Publicado / 📝 Rascunho), link **Visualizar** (nova aba), botões contextuais: se rascunho → *Publicar* + *Salvar*; se publicado → *Salvar Alterações* + *Voltar para Rascunho*.
5. **Lista de artigos (`posts/index`)** — nova coluna de status com badges, botão **Publicar** rápido para rascunhos, link **Ver** em nova aba.
6. **Homepage** — agora usa `Post::published()` — só exibe posts publicados.
7. **Página do artigo (`posts/show`)** — rascunhos retornam 404 para visitantes; o dono vê um banner amarelo de pré-visualização com link direto para editar/publicar. Banner colocado fora do grid de artigo para não quebrar o layout de duas colunas (artigo + sumário).

#### Desafio: múltiplos elementos raiz no Livewire
O banner de rascunho inicialmente foi colocado como primeiro filho do grid (`col-span-full`), o que deslocou o conteúdo principal para a coluna do sumário. O Livewire também exige um único elemento raiz no componente. Solução: todo o componente `show.blade.php` envolvido em um único `<div>`, com o banner posicionado antes do grid (fora dele).

---
*Assinado: Copilot CLI & Demóstenes*
