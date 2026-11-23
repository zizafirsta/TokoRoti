<?php

namespace Tests\Feature\Requirements;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-005 Manage Stock (Admin): RQ-018 s/d RQ-020 */
class ManageStockRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    /** RQ-018: tambah stok + alasan. */
    public function test_rq018_admin_can_add_stock_with_reason(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['stock' => 10]);

        $response = $this->actingAs($admin)->post("/admin/stock/{$product->id}", [
            'type' => 'add', 'quantity' => 15, 'reason' => 'Produksi pagi',
        ]);

        $response->assertRedirect(route('admin.stock.index'))->assertSessionHas('success');
        $this->assertSame(25, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id, 'user_id' => $admin->id, 'type' => 'add',
            'quantity' => 15, 'stock_before' => 10, 'stock_after' => 25, 'reason' => 'Produksi pagi',
        ]);
    }

    /** RQ-018: kurangi stok + alasan. */
    public function test_rq018_admin_can_reduce_stock_with_reason(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $this->actingAs($this->makeAdmin())->post("/admin/stock/{$product->id}", [
            'type' => 'reduce', 'quantity' => 4, 'reason' => 'Roti rusak',
        ])->assertSessionHas('success');

        $this->assertSame(6, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $product->id, 'type' => 'reduce', 'stock_after' => 6]);
    }

    /** RQ-018: alasan wajib. */
    public function test_rq018_reason_is_required(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $response = $this->actingAs($this->makeAdmin())->post("/admin/stock/{$product->id}", [
            'type' => 'add', 'quantity' => 5, 'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    /** RQ-018: jumlah / jenis tidak valid. */
    public function test_rq018_invalid_quantity_or_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['stock' => 10]);

        $this->actingAs($admin)->post("/admin/stock/{$product->id}", ['type' => 'add', 'quantity' => 0, 'reason' => 'x x x'])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post("/admin/stock/{$product->id}", ['type' => 'add', 'quantity' => 'abc', 'reason' => 'x x x'])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post("/admin/stock/{$product->id}", ['type' => 'set', 'quantity' => 5, 'reason' => 'x x x'])->assertSessionHasErrors('type');

        $this->assertSame(10, $product->fresh()->stock);
    }

    /** RQ-019: pengurangan melebihi stok ditolak, stok tetap. */
    public function test_rq019_stock_cannot_become_negative(): void
    {
        $product = $this->makeProduct(['stock' => 3]);

        $response = $this->actingAs($this->makeAdmin())->post("/admin/stock/{$product->id}", [
            'type' => 'reduce', 'quantity' => 4, 'reason' => 'Salah hitung',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    /** RQ-019 (batas): mengurangi tepat sampai 0 diperbolehkan. */
    public function test_rq019_stock_can_be_reduced_to_exactly_zero(): void
    {
        $product = $this->makeProduct(['stock' => 3]);

        $this->actingAs($this->makeAdmin())->post("/admin/stock/{$product->id}", [
            'type' => 'reduce', 'quantity' => 3, 'reason' => 'Habis terjual langsung',
        ])->assertSessionHas('success');

        $this->assertSame(0, $product->fresh()->stock);
    }

    /** RQ-019 (white-box, model): adjustStock() melempar DomainException bila hasil negatif. */
    public function test_rq019_model_throws_domain_exception_when_negative(): void
    {
        $product = $this->makeProduct(['stock' => 2]);

        $this->expectException(\DomainException::class);

        $product->adjustStock('reduce', 5, 'Tes');
    }

    /** RQ-019 (white-box, model): tipe / jumlah / alasan tidak valid. */
    public function test_rq019_model_rejects_invalid_arguments(): void
    {
        $product = $this->makeProduct(['stock' => 2]);

        foreach ([['bogus', 1, 'a'], ['add', 0, 'a'], ['add', 1, '   ']] as [$type, $qty, $reason]) {
            try {
                $product->adjustStock($type, $qty, $reason);
                $this->fail('InvalidArgumentException diharapkan untuk: ' . json_encode([$type, $qty, $reason]));
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->assertSame(2, $product->fresh()->stock);
    }

    /** RQ-020: dashboard menampilkan peringatan untuk stok <= batas minimum. */
    public function test_rq020_dashboard_warns_when_stock_reaches_minimum(): void
    {
        $low = $this->makeProduct(['name' => 'Roti Menipis', 'stock' => 5, 'min_stock' => 5]); // tepat di batas
        $below = $this->makeProduct(['name' => 'Roti Hampir Habis', 'stock' => 1, 'min_stock' => 5]);
        $safe = $this->makeProduct(['name' => 'Roti Aman', 'stock' => 50, 'min_stock' => 5]);

        $response = $this->actingAs($this->makeAdmin())->get('/admin/dashboard');

        $response->assertOk()
            ->assertSee('Peringatan')
            ->assertSee('Roti Menipis')
            ->assertSee('Roti Hampir Habis')
            ->assertDontSee('Roti Aman');
    }

    /** RQ-020: tanpa produk menipis tidak ada peringatan. */
    public function test_rq020_no_warning_when_all_stock_is_safe(): void
    {
        $this->makeProduct(['stock' => 50, 'min_stock' => 5]);

        $this->actingAs($this->makeAdmin())->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('stoknya menipis')
            ->assertSee('Semua stok produk masih aman');
    }

    /** RQ-020 (white-box, scope): lowStock() memakai perbandingan stock <= min_stock. */
    public function test_rq020_low_stock_scope(): void
    {
        $a = $this->makeProduct(['stock' => 5, 'min_stock' => 5]);
        $b = $this->makeProduct(['stock' => 6, 'min_stock' => 5]);

        $ids = Product::lowStock()->pluck('id')->all();

        $this->assertContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids);
    }
}
