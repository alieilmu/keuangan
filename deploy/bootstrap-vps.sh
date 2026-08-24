#!/usr/bin/env bash
#
# Bootstrap Dashboard Keuangan di VPS Ubuntu.
# Jalankan sekali saja pada server yang masih bersih:
#
#   sudo bash deploy/bootstrap-vps.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/keuangan}"
REPO_URL="${REPO_URL:-}"

log() { printf '\n\033[1;32m==>\033[0m %s\n' "$1"; }
die() { printf '\n\033[1;31mGagal:\033[0m %s\n' "$1" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Jalankan dengan sudo/root."

# --- 1. Docker ------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    log "Memasang Docker Engine + Compose plugin"
    apt-get update -qq
    apt-get install -y -qq ca-certificates curl git
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io \
        docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
else
    log "Docker sudah terpasang: $(docker --version)"
fi

# --- 2. Kode aplikasi -----------------------------------------------------
if [[ ! -d "$APP_DIR/.git" ]]; then
    [[ -n "$REPO_URL" ]] || die "Set REPO_URL, contoh: REPO_URL=https://github.com/user/repo.git sudo -E bash $0"
    log "Meng-clone repo ke $APP_DIR"
    git clone "$REPO_URL" "$APP_DIR"
else
    log "Memperbarui kode di $APP_DIR"
    git -C "$APP_DIR" pull --ff-only
fi

cd "$APP_DIR"

# --- 3. Berkas .env -------------------------------------------------------
if [[ ! -f .env ]]; then
    log "Membuat .env dari contoh + password acak"
    cp .env.docker.example .env

    db_pass="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
    db_root="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"

    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${db_pass}|"      .env
    sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${db_root}|" .env

    echo "  DB_PASSWORD & DB_ROOT_PASSWORD dibuat acak dan disimpan di .env"
else
    log ".env sudah ada, dibiarkan apa adanya"
fi

chmod 600 .env

# --- 4. Build & jalankan --------------------------------------------------
log "Membangun image (butuh beberapa menit pada build pertama)"
docker compose build

if ! grep -qE '^APP_KEY=.+' .env; then
    log "Membuat APP_KEY"
    key="$(docker compose run --rm --no-deps app php artisan key:generate --show)"
    sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" .env
fi

if ! grep -qE '^VAPID_PUBLIC_KEY=.+' .env; then
    log "Membuat VAPID keys untuk push notification"
    docker compose run --rm --no-deps app php artisan webpush:vapid || \
        echo "  Lewati - isi VAPID_* manual bila push notification dibutuhkan."
fi

log "Menyalakan seluruh service"
docker compose up -d

log "Menunggu database siap"
for _ in $(seq 1 60); do
    if docker compose exec -T db mysqladmin ping -h 127.0.0.1 --silent >/dev/null 2>&1; then break; fi
    sleep 2
done

log "Menjalankan migrasi"
docker compose exec -T app php artisan migrate --force

log "Meng-cache konfigurasi"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan event:cache

port="$(grep -E '^APP_PORT=' .env | cut -d= -f2 || echo 8080)"

log "Selesai. Aplikasi berjalan di http://$(hostname -I | awk '{print $1}'):${port:-8080}"
echo
echo "Langkah berikutnya:"
echo "  1. Buat user lewat halaman /register, atau jalankan:"
echo "     docker compose exec app php artisan db:seed --force"
echo "  2. Pasang reverse proxy + HTTPS bila memakai domain."
echo "  3. Cek log: docker compose logs -f app"
