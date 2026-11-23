<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    /** Semua status yang valid (sesuai enum di migration orders). */
    public const STATUS_LABELS = [
        'pending'    => 'Menunggu Pembayaran',
        'paid'       => 'Dibayar',
        'processing' => 'Diproses',
        'ready'      => 'Siap Diambil',
        'shipped'    => 'Dikirim',
        'completed'  => 'Selesai',
        'cancelled'  => 'Dibatalkan',
    ];

    /** Status yang dihitung sebagai penjualan pada laporan (RQ-029, RQ-030). */
    public const SALES_STATUSES = ['paid', 'processing', 'ready', 'shipped', 'completed'];

    protected $fillable = [
        'user_id',
        'address_id',
        'promo_id',
        'invoice_no',
        'fulfillment_type',
        'subtotal',
        'discount',
        'shipping_fee',
        'total',
        'status',
        'scheduled_at',
        'note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Status berikutnya dalam alur normal (RQ-021):
     * pending -> paid -> processing -> ready (pickup) / shipped (delivery) -> completed.
     * Mengembalikan null bila pesanan sudah berada di status akhir.
     */
    public function nextStatus(): ?string
    {
        return match ($this->status) {
            'pending'    => 'paid',
            'paid'       => 'processing',
            'processing' => $this->fulfillment_type === 'delivery' ? 'shipped' : 'ready',
            'ready', 'shipped' => 'completed',
            default      => null, // completed / cancelled
        };
    }

    /** Pesanan masih boleh dibatalkan selama belum selesai / belum dibatalkan. */
    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    /** Apakah perpindahan ke $status valid menurut urutan (RQ-022)? */
    public function canTransitionTo(string $status): bool
    {
        if ($status === 'cancelled') {
            return $this->canBeCancelled();
        }

        return $status === $this->nextStatus();
    }

    /**
     * Ubah status pesanan + catat ke order_status_logs.
     * Jika menjadi "cancelled", stok produk dikembalikan (hanya sekali).
     * Mengembalikan false bila status tidak berubah.
     */
    public function changeStatus(string $status, ?int $changedBy = null, ?string $reason = null): bool
    {
        if ($this->status === $status) {
            return false;
        }

        DB::transaction(function () use ($status, $changedBy, $reason) {
            $this->update(['status' => $status]);
            $this->statusLogs()->create([
                'status'     => $status,
                'changed_by' => $changedBy,
                'reason'     => $reason,
            ]);

            $payment = $this->payment;

            if ($status === 'cancelled') {
                $this->restock();
                $payment?->update(['status' => 'cancel']);
            } elseif (in_array($status, ['paid', 'completed'], true) && $payment && $payment->status === 'pending') {
                // Pembayaran dianggap diterima saat admin menandai Dibayar / Selesai
                $payment->update(['status' => 'settlement', 'paid_at' => now()]);
            }
        });

        return true;
    }

    /** Kembalikan stok semua item pesanan ini. */
    public function restock(): void
    {
        $this->loadMissing('items');

        foreach ($this->items as $item) {
            Product::whereKey($item->product_id)->increment('stock', $item->quantity);
        }
    }
}
