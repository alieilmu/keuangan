#!/usr/bin/env bash
#
# Deploy versi terbaru: tarik kode, build ulang, migrasi, restart.
#
#   cd /opt/keuangan && sudo bash deploy/update.sh
#
set -euo pipefail

log() { printf '\n\033[1;32m==>\033[0m %s\n' "$1"; }

cd "$(dirname "$0")/.."

log "Menarik kode terbaru"
git pull --ff-only

log "Membangun ulang image"
docker compose build

log "Mengaktifkan mode maintenance"
docker compose exec -T app php artisan down --render="errors::503" || true

log "Menyalakan service versi baru"
docker compose up -d

log "Menjalankan migrasi"
docker compose exec -T app php artisan migrate --force

log "Menyegarkan cache"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan event:cache

log "Keluar dari mode maintenance"
docker compose exec -T app php artisan up

log "Selesai"
docker compose ps
