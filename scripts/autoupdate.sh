#!/usr/bin/env bash
# Usage: bash /path/to/blog/scripts/autoupdate.sh [/path/to/blog]
set -Eeuo pipefail
export PATH="/usr/local/bin:/usr/bin:/bin:${PATH:-}"
export GIT_TERMINAL_PROMPT=0

log() { printf '[%s] %s\n' "$(date -Is)" "$*"; }
trap 'code=$?; log "ERRO na linha $LINENO (status $code). O deploy será tentado novamente; consulte o log antes de tirar o site de manutenção."; exit "$code"' ERR

project_dir="${1:-$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)}"
cd -- "$project_dir"
for command in git docker flock; do
    command -v "$command" >/dev/null || { log "Comando necessário: $command"; exit 1; }
done
git_dir=$(git rev-parse --absolute-git-dir)
exec 9>"$git_dir/autoupdate.lock"
flock -n 9 || exit 0
state="$git_dir/autoupdate.deployed"
compose=(docker compose -f docker-compose.prod.yml)
[[ -f .env && -f Dockerfile.prod ]] || { log 'Configure .env e Dockerfile.prod antes de agendar.'; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=normal)" ]] || {
    log 'Atualização cancelada: existem alterações locais ou arquivos não rastreados. Nenhum arquivo foi descartado.'
    exit 1
}
branch=$(git symbolic-ref --quiet --short HEAD) || { log 'HEAD precisa estar em uma branch.'; exit 1; }
upstream=$(git rev-parse --abbrev-ref --symbolic-full-name '@{upstream}')
remote=$(git config --get "branch.$branch.remote")
[[ "$remote" != '.' ]] || { log 'Configure um upstream remoto.'; exit 1; }
git fetch --prune "$remote"
target=$(git rev-parse "$upstream")
git merge-base --is-ancestor HEAD "$target" || {
    log 'A branch local divergiu ou tem commits não publicados. Resolva manualmente.'
    exit 1
}
if [[ -f "$state" && "$(cat "$state")" == "$target" && "$(git rev-parse HEAD)" == "$target" ]]; then
    exit 0
fi

log "Atualizando $project_dir para $target"
# Maintenance remains enabled on failure to avoid serving a partial deployment.
"${compose[@]}" exec -T app php artisan down --retry=60
git merge --ff-only "$target"
"${compose[@]}" build app
# Named volumes retain old dependencies despite rebuilding the image.
"${compose[@]}" run --rm --no-deps app composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
"${compose[@]}" run --rm --no-deps app npm ci --no-audit --no-fund
"${compose[@]}" run --rm --no-deps app npm run build
"${compose[@]}" run --rm --no-deps app php artisan optimize:clear
"${compose[@]}" run --rm --no-deps app php artisan migrate --force
"${compose[@]}" run --rm --no-deps app php artisan optimize
"${compose[@]}" up -d --no-deps --force-recreate app worker
# Nginx must resolve the recreated app container again.
"${compose[@]}" restart nginx
"${compose[@]}" exec -T app php artisan up
printf '%s\n' "$target" > "$state"
log "Deploy concluído: $target"
