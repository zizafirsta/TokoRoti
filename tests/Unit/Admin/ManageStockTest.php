<?php

namespace Tests\Unit\Admin;

use App\Http\Controllers\Admin\StockController;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * White-box test fitur MANAGE STOCK
 * Unit: StockController::index/adjust dan Product::adjustStock
 *
 * Test Double:
 *  - MOCK : Product::adjustStock() dipantau (dipanggil sekali dengan argumen benar / tidak dipanggil)
 *  - STUB : Product::adjustStock() dipaksa melempar DomainException (stok negatif)
 *  - FAKE : SQLite in-memory untuk pengujian Product::adjustStock sungguhan
 */
class ManageStockTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    private function adjustRequest(array $override = []): Request
    {
        return Request::create('/admin/stock/1', 'POST', array_merge([
            'type'     => 'add',
            'quantity' => 5,
            'reason'   => 'Restock harian',
        ], $override));
    }

    /** Product palsu (partial mock) berisi nama & stok, tanpa menyentuh DB. */
    private function fakeProduct(): \Mockery\LegacyMockInterface
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->forceFill(['name' => 'Roti Tawar', 'stock' => 15]);

        return $product;
    }

    /** TC-01: tambah stok -> adjustStock dipanggil sekali dengan argumen benar (MOCK), flash sukses. */
    public function test_tc01_adjust_add_memanggil_model_dan_flash_sukses(): void
    {
        // Arrange
        $product = $this->fakeProduct();
        $product->shouldReceive('adjustStock')->once()
            ->with('add', 5, 'Restock harian', null);                 // MOCK

        // Act
        $response = app(StockController::class)->adjust($this->adjustRequest(), $product);

        // Assert
        $this->assertStringEndsWith('/admin/stock', $response->getTargetUrl());
        $this->assertSame('Stok Roti Tawar berhasil ditambah 5. Stok sekarang: 15.', $response->getSession()->get('success'));
    }

    /** TC-02: kurangi stok -> kata kerja "dikurangi". */
    public function test_tc02_adjust_reduce_memakai_kata_dikurangi(): void
    {
        // Arrange
        $product = $this->fakeProduct();
        $product->shouldReceive('adjustStock')->once()->with('reduce', 3, 'Roti basi', null);

        // Act
        $response = app(StockController::class)->adjust(
            $this->adjustRequest(['type' => 'reduce', 'quantity' => 3, 'reason' => 'Roti basi']),
            $product
        );

        // Assert
        $this->assertStringContainsString('berhasil dikurangi 3', $response->getSession()->get('success'));
    }

    /** TC-03: stok jadi negatif -> DomainException ditangkap, flash error (STUB). */
    public function test_tc03_stok_negatif_menampilkan_error(): void
    {
        // Arrange
        $product = $this->fakeProduct();
        $product->shouldReceive('adjustStock')->once()
            ->andThrow(new \DomainException('Stok tidak boleh negatif.')); // STUB

        // Act
        $response = app(StockController::class)->adjust($this->adjustRequest(['type' => 'reduce']), $product);

        // Assert
        $this->assertSame('Stok tidak boleh negatif.', $response->getSession()->get('error'));
        $this->assertNull($response->getSession()->get('success'));
    }

    /** TC-04..: validasi menolak input salah, adjustStock tidak boleh dipanggil. */
    #[DataProvider('invalidProvider')]
    public function test_tc04_input_tidak_valid_ditolak(array $override, string $errorKey): void
    {
        // Arrange
        $product = $this->fakeProduct();
        $product->shouldReceive('adjustStock')->never();

        // Act & Assert
        try {
            app(StockController::class)->adjust($this->adjustRequest($override), $product);
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->errors());
        }
    }

    public static function invalidProvider(): array
    {
        return [
            'tipe salah'        => [['type' => 'hapus'], 'type'],
            'tipe kosong'       => [['type' => ''], 'type'],
            'jumlah nol'        => [['quantity' => 0], 'quantity'],
            'jumlah desimal'    => [['quantity' => 1.5], 'quantity'],
            'jumlah kosong'     => [['quantity' => ''], 'quantity'],
            'alasan kosong'     => [['reason' => ''], 'reason'],
            'alasan terlalu pendek' => [['reason' => 'ab'], 'reason'],
        ];
    }

    /** TC-05: integrasi sungguhan (DB palsu) -> stok bertambah & riwayat dicatat. */
    public function test_tc05_adjust_sungguhan_menambah_stok_dan_mencatat_riwayat(): void
    {
        // Arrange
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $product = $this->makeProduct(['stock' => 10]);

        // Act
        app(StockController::class)->adjust($this->adjustRequest(), $product);

        // Assert
        $this->assertSame(15, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id'   => $product->id,
            'user_id'      => $admin->id,
            'type'         => 'add',
            'quantity'     => 5,
            'stock_before' => 10,
            'stock_after'  => 15,
        ]);
    }

    /** TC-06: pengurangan melebihi stok -> stok tidak berubah & tidak ada riwayat. */
    public function test_tc06_reduce_melebihi_stok_ditolak_tanpa_mengubah_data(): void
    {
        // Arrange
        $this->actingAs($this->makeAdmin());
        $product = $this->makeProduct(['stock' => 3]);

        // Act
        $response = app(StockController::class)->adjust(
            $this->adjustRequest(['type' => 'reduce', 'quantity' => 10]),
            $product
        );

        // Assert
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, StockAdjustment::count());
        $this->assertStringContainsString('tidak boleh negatif', $response->getSession()->get('error'));
    }

    /** TC-07: Product::adjustStock menolak tipe tidak valid. */
    public function test_tc07_model_tipe_tidak_valid_melempar_exception(): void
    {
        // Arrange
        $product = $this->makeProduct();

        $this->expectException(\InvalidArgumentException::class);

        // Act
        $product->adjustStock('hapus', 1, 'alasan');
    }

    /** TC-08: Product::adjustStock menolak jumlah < 1. */
    public function test_tc08_model_jumlah_kurang_dari_satu_melempar_exception(): void
    {
        // Arrange
        $product = $this->makeProduct();

        $this->expectException(\InvalidArgumentException::class);

        // Act
        $product->adjustStock('add', 0, 'alasan');
    }

    /** TC-09: Product::adjustStock menolak alasan kosong/spasi. */
    public function test_tc09_model_alasan_kosong_melempar_exception(): void
    {
        // Arrange
        $product = $this->makeProduct();

        $this->expectException(\InvalidArgumentException::class);

        // Act
        $product->adjustStock('add', 1, '   ');
    }

    /** TC-10: index() mengembalikan view dengan daftar produk dan riwayat. */
    public function test_tc10_index_mengembalikan_view_stok(): void
    {
        // Arrange
        $product = $this->makeProduct();

        // Act
        $product->adjustStock('add', 2, 'Tes riwayat');

        // Assert
        $view = app(StockController::class)->index();

        $this->assertSame('admin.stock.index', $view->name());
        $this->assertCount(1, $view->getData()['products']);
        $this->assertCount(1, $view->getData()['history']);
    }

    /** TC-11: Product::adjustStock tipe reduce valid -> stok berkurang & riwayat bertipe reduce. */
    public function test_tc11_model_reduce_valid_mengurangi_stok(): void
    {
        // Arrange
        $product = $this->makeProduct(['stock' => 10]);

        // Act
        $log = $product->adjustStock('reduce', 3, 'Roti basi', null);

        // Assert
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertSame('reduce', $log->type);
        $this->assertEquals(10, $log->stock_before);
        $this->assertEquals(7, $log->stock_after);
    }
}
