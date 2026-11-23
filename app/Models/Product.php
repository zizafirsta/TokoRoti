<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'min_stock',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * URL gambar produk.
     * - Path "images/..." = file bawaan di public/images
     * - Path lain = hasil upload admin di storage/app/public (butuh `php artisan storage:link`)
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'images/')
            ? asset($this->image)
            : asset('storage/' . $this->image);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /** Produk yang stoknya sudah mencapai / di bawah batas minimum (RQ-020). */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    /** Produk pernah dipesan -> tidak boleh dihapus, hanya dinonaktifkan (RQ-016, RQ-017). */
    public function hasOrderHistory(): bool
    {
        return $this->orderItems()->exists();
    }

    /**
     * Tambah / kurangi stok disertai alasan, dan catat ke stock_adjustments.
     * Stok tidak boleh menjadi negatif (RQ-019).
     *
     * @param  string  $type  'add' atau 'reduce'
     * @throws \InvalidArgumentException bila tipe / jumlah / alasan tidak valid
     * @throws \DomainException bila pengurangan membuat stok negatif
     */
    public function adjustStock(string $type, int $quantity, string $reason, ?int $userId = null): StockAdjustment
    {
        if (! in_array($type, ['add', 'reduce'], true)) {
            throw new \InvalidArgumentException('Tipe penyesuaian stok tidak valid.');
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Jumlah penyesuaian minimal 1.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Alasan penyesuaian stok wajib diisi.');
        }

        return DB::transaction(function () use ($type, $quantity, $reason, $userId) {
            // Kunci baris produk supaya dua admin tidak menimpa stok satu sama lain
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $before = (int) $fresh->stock;
            $after = $type === 'add' ? $before + $quantity : $before - $quantity;

            if ($after < 0) {
                throw new \DomainException("Stok {$fresh->name} tidak boleh negatif (stok saat ini {$before}, pengurangan {$quantity}).");
            }

            $fresh->update(['stock' => $after]);
            $this->stock = $after;

            return $fresh->stockAdjustments()->create([
                'user_id'      => $userId,
                'type'         => $type,
                'quantity'     => $quantity,
                'stock_before' => $before,
                'stock_after'  => $after,
                'reason'       => trim($reason),
            ]);
        });
    }
}
