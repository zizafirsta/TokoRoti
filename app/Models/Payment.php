<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /** Label metode pembayaran. Metode lama (mis. "midtrans") tetap tampil apa adanya. */
    public const METHOD_LABELS = [
        'bank_transfer' => 'Transfer Bank',
        'qris'          => 'QRIS',
        'cash'          => 'Bayar di Tempat (Tunai)',
    ];

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'gateway_ref',
        'payer_name',
        'proof',
        'proof_uploaded_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'proof_uploaded_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Rekening tujuan transfer yang sudah diisi di .env. */
    public static function banks(): array
    {
        return array_values(array_filter(
            config('toko.banks', []),
            fn ($bank) => ! empty($bank['number'])
        ));
    }

    /** URL gambar QRIS toko, atau null bila file belum diletakkan di folder public. */
    public static function qrisImageUrl(): ?string
    {
        $path = config('toko.qris_image');

        if (! $path || ! file_exists(public_path($path))) {
            return null;
        }

        return asset($path);
    }

    /** Metode pembayaran yang saat ini bisa dipilih pelanggan. */
    public static function availableMethods(): array
    {
        $methods = [];

        if (! empty(self::banks())) {
            $methods['bank_transfer'] = [
                'label' => self::METHOD_LABELS['bank_transfer'],
                'hint'  => 'Transfer ke rekening toko, lalu unggah bukti transfer.',
            ];
        }

        if (self::qrisImageUrl()) {
            $methods['qris'] = [
                'label' => self::METHOD_LABELS['qris'],
                'hint'  => 'Scan QRIS dengan e-wallet / m-banking, lalu unggah bukti bayar.',
            ];
        }

        $methods['cash'] = [
            'label' => self::METHOD_LABELS['cash'],
            'hint'  => 'Bayar tunai saat pesanan diambil atau diantar.',
        ];

        return $methods;
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHOD_LABELS[$this->method] ?? ucwords(str_replace('_', ' ', (string) $this->method));
    }

    /** Metode yang mewajibkan bukti pembayaran. */
    public function needsProof(): bool
    {
        return in_array($this->method, ['bank_transfer', 'qris'], true);
    }

    /** Bukti sudah diunggah, menunggu dicek admin. */
    public function isAwaitingVerification(): bool
    {
        return $this->status === 'pending' && ! empty($this->proof);
    }
}
