<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Batalkan pesanan yang tidak dibayar melewati batas waktu (stok otomatis kembali).
// Butuh scheduler aktif: `php artisan schedule:work` (lokal) atau cron `schedule:run` (server).
Schedule::command('orders:cancel-expired')->hourly();
