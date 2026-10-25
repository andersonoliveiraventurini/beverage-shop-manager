#!/usr/bin/env bash
# Publica a FA Distribuidora no servidor openclaw (/srv/fa).
# O .env de produção mora só no servidor; este script nunca o sobrescreve.
set -euo pipefail
cd "$(dirname "$0")/.."

npm run build

rsync -az --delete \
  --exclude .git --exclude node_modules --exclude vendor --exclude .env \
  --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' --exclude 'database/*.sqlite' \
  --exclude public/hot \
  ./ openclaw:/srv/fa/

ssh openclaw 'cd /srv/fa \
  && docker compose -f docker-compose.openclaw.yml build app \
  && docker compose -f docker-compose.openclaw.yml up -d \
  && docker exec fa_app php artisan migrate --force \
  && docker exec fa_app php artisan optimize \
  && docker exec fa_app php artisan filament:optimize'

echo "Publicado: https://fa.avssistemas.com.br"
