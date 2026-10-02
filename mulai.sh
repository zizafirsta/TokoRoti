#!/usr/bin/env bash
# Menjalankan Toko Roti di Linux/macOS: ./mulai.sh
set -e
cd "$(dirname "$0")"

command -v php >/dev/null 2>&1 || { echo "[ERROR] PHP tidak ditemukan."; exit 1; }

[ -f vendor/autoload.php ] || composer install

echo "[1/3] Migrasi database..."
php artisan migrate --force || { echo "[ERROR] Migrasi gagal. Pastikan database sudah dibuat dan .env benar."; exit 1; }

if [ ! -f storage/app/.seeded ]; then
    echo "Mengisi data contoh (akun admin, kategori, produk)..."
    php artisan db:seed --force && touch storage/app/.seeded
fi

echo "[2/3] Menautkan folder storage..."
php artisan storage:link || true

echo "[3/3] Server berjalan di http://localhost:8000"
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan serve
