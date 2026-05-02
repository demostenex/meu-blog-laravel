# TODO — Cloudflare R2 Storage

Implementação da "Soberania de Storage": migrar capas e assets para o Cloudflare R2
(S3-compatible, 10 GB grátis, zero egresso), mantendo fallback local via `.env`.

---

## Progresso

- [x] **1. Dependência S3** — `league/flysystem-aws-s3-v3` adicionada ao `composer.json`
- [x] **2. Helper `image_url()`** — `app/helpers.php` criado e registrado no autoload do composer
- [x] **3. Disco `r2` configurado** — `config/filesystems.php` com chave `image_disk` e disco `r2`
- [x] **4. `.env.example` atualizado** — `R2_*` e `IMAGE_DISK` documentados
- [x] **5. `ImageService.php`** — `storeCompressed` e `storeFromBase64` usam disco configurável
- [x] **6. Views atualizadas** — `asset('storage/...')` → `image_url()` em todas as views
      → show, home, about, navigation, create, edit, profile, feed, layouts (app/guest/blog)
- [x] **7. Comando `php artisan media:sync-to-r2`** — copia assets locais para R2 sem regenerar
      → Opções: `--dry-run` (lista sem enviar) e `--force` (sobrescreve existentes)
- [x] **8. Dashboard** — seção "Sincronizar para R2" com botão e log de resultado em tempo real
- [x] **9. Testes** — unitários (helper, ImageService) e feature (comando sync)

---

## Ação necessária após fazer o pull

```bash
# 1. Instalar a dependência S3
composer require league/flysystem-aws-s3-v3

# 2. Preencher o .env com as credenciais R2
IMAGE_DISK=r2
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=nome-do-bucket
R2_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
R2_URL=https://pub-xxxxxxxx.r2.dev

# 3. Limpar cache de config
php artisan config:clear

# 4. Sincronizar assets existentes (ou usar o botão no Dashboard)
php artisan media:sync-to-r2

# 5. Confirmar que imagens carregam, depois pode remover /storage/app/public/ se quiser
```

---

## Arquitetura

```
IMAGE_DISK=public  → Storage::disk('public')  → /storage/app/public/  → /storage/ via symlink
IMAGE_DISK=r2      → Storage::disk('r2')      → Cloudflare R2 bucket  → URL pública R2
```

**`image_url($path)`** — helper global que gera a URL correta para qualquer disco.

**`BackupRun` e `OptimizeImages`** — continuam no disco `public` (precisam de acesso local ao filesystem).
