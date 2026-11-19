@echo off
setlocal
cd /d "%~dp0"
title Toko Roti - Server Lokal

where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP tidak ditemukan. Aktifkan PHP dari XAMPP/Laragon atau tambahkan PHP ke PATH.
    pause
    exit /b 1
)

if not exist vendor\autoload.php (
    echo Folder vendor belum ada, menjalankan composer install...
    call composer install
)

echo.
echo [1/3] Migrasi database...
php artisan migrate --force
if errorlevel 1 (
    echo.
    echo [ERROR] Migrasi gagal. Pastikan MySQL sudah menyala dan database db_toko_roti sudah dibuat. Cek pengaturan di file .env
    pause
    exit /b 1
)

if not exist storage\app\.seeded (
    echo Mengisi data contoh - akun admin, kategori, dan produk...
    php artisan db:seed --force
    if not errorlevel 1 echo ok> storage\app\.seeded
)

echo [2/3] Menautkan folder storage untuk foto produk...
php artisan storage:link

echo [3/3] Menjalankan server di http://localhost:8000
php artisan optimize:clear >nul 2>nul
start "" http://localhost:8000
php artisan serve
