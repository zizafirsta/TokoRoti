<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Pembuat data uji bersama untuk test requirement RQ-001 s/d RQ-031. */
trait BuildsFixtures
{
    protected function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function makeCustomer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function makeCategory(string $name = 'Bread'): Category
    {
        return Category::firstOrCreate(['slug' => strtolower($name)], ['name' => $name]);
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->makeCategory()->id,
            'name'        => 'Roti Tawar ' . uniqid(),
            'description' => 'Roti tawar lembut.',
            'price'       => 15000,
            'stock'       => 20,
            'min_stock'   => 5,
            'is_active'   => true,
        ], $overrides));
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines  pasangan [produk, qty]
     */
    protected function makeOrder(User $user, string $status = 'pending', array $lines = [], array $overrides = []): Order
    {
        $createdAt = $overrides['created_at'] ?? Carbon::now();
        unset($overrides['created_at']);

        $order = new Order(array_merge([
            'user_id'          => $user->id,
            'invoice_no'       => 'INV-' . strtoupper(uniqid()),
            'fulfillment_type' => 'pickup',
            'subtotal'         => 30000,
            'discount'         => 0,
            'shipping_fee'     => 0,
            'total'            => 30000,
            'status'           => $status,
        ], $overrides));
        $order->created_at = $createdAt;
        $order->save();

        foreach ($lines as [$product, $qty]) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $qty,
                'price'      => $product->price,
            ]);
        }

        return $order;
    }
}
