<?php

/*
|--------------------------------------------------------------------------
| Pengaturan Toko & Pembayaran Manual
|--------------------------------------------------------------------------
| Semua nilai dibaca dari file .env (lihat bagian "TOKO" di .env.example).
| Sistem pembayaran memakai transfer bank / QRIS / bayar di tempat, sehingga
| tidak membutuhkan payment gateway pihak ketiga.
*/

return [

    'name' => env('STORE_NAME', 'Toko Roti'),

    // Nomor WhatsApp toko, format internasional tanpa tanda + (contoh: 6281234567890).
    // Dikosongkan = tombol "Konfirmasi via WhatsApp" tidak ditampilkan.
    'whatsapp' => preg_replace('/\D+/', '', (string) env('STORE_WHATSAPP', '')),

    'shipping_fee' => (int) env('SHIPPING_FEE', 10000),

    // Pesanan yang belum dibayar lebih dari sekian jam akan dibatalkan otomatis (stok kembali).
    'unpaid_expiry_hours' => (int) env('UNPAID_ORDER_EXPIRY_HOURS', 24),

    // Gambar QRIS toko (relatif terhadap folder public). Jika file tidak ada, opsi QRIS disembunyikan.
    'qris_image' => env('QRIS_IMAGE', 'images/qris.png'),

    // Rekening tujuan transfer. Rekening tanpa nomor akan diabaikan.
    'banks' => [
        [
            'bank'   => env('BANK1_NAME', 'BCA'),
            'number' => env('BANK1_NUMBER'),
            'holder' => env('BANK1_HOLDER'),
        ],
        [
            'bank'   => env('BANK2_NAME'),
            'number' => env('BANK2_NUMBER'),
            'holder' => env('BANK2_HOLDER'),
        ],
    ],

];
