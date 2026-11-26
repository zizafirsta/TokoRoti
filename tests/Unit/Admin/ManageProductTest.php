<?php

namespace Tests\Unit\Admin;

use App\Http\Controllers\Admin\ProductController;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * White-box test fitur MANAGE PRODUCT
 * Unit: ProductController (index, create, store, edit, update, destroy, toggleActive)
 *
 * Test Double:
 *  - FAKE  : Storage::fake('public') = disk palsu di memori/temp; SQLite in-memory = DB palsu
 *  - MOCK  : Storage::shouldReceive('disk->delete')->once() memastikan file lama dihapus
 *  - FAKE  : UploadedFile::fake() = file upload palsu (PNG 1x1), file asli tidak disentuh
 */
class ManageProductTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    /** PNG 1x1 valid, tidak butuh ekstensi GD. */
    private function png(string $name = 'roti.png'): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function validData(array $override = []): array
    {
        return array_merge([
            'category_id' => $this->makeCategory()->id,
            'name'        => 'Croissant',
            'description' => 'Renyah dan harum mentega.',
            'price'       => 18000,
            'stock'       => 10,
            'min_stock'   => 3,
            'is_active'   => 1,
        ], $override);
    }

    private function callStore(array $data, ?UploadedFile $image = null)
    {
        $files = $image ? ['image' => $image] : [];

        return app(ProductController::class)->store(Request::create('/admin/products', 'POST', $data, [], $files));
    }

    private function callUpdate(Product $product, array $data, ?UploadedFile $image = null)
    {
        $files = $image ? ['image' => $image] : [];

        return app(ProductController::class)->update(Request::create('/admin/products/' . $product->id, 'PUT', $data, [], $files), $product);
    }

    /** TC-01: data valid + foto -> produk tersimpan, foto masuk storage, redirect sukses. */
    public function test_tc01_store_valid_menyimpan_produk_dan_foto(): void
    {
        // Arrange
        Storage::fake('public'); // FAKE

        // Act
        $response = $this->callStore($this->validData(), $this->png());

        // Assert
        $product = Product::where('name', 'Croissant')->first();
        $this->assertNotNull($product);
        Storage::disk('public')->assertExists($product->image);
        $this->assertSame(3, $product->min_stock);
        $this->assertTrue($product->is_active);
        $this->assertStringEndsWith('/admin/products', $response->getTargetUrl());
        $this->assertSame('Produk berhasil ditambahkan!', $response->getSession()->get('success'));
    }

    /** TC-02: min_stock & is_active tidak dikirim -> default min_stock 5, produk nonaktif. */
    public function test_tc02_store_tanpa_min_stock_dan_is_active_memakai_default(): void
    {
        // Arrange
        Storage::fake('public');
        $data = $this->validData();
        unset($data['min_stock'], $data['is_active']);

        // Act
        $this->callStore($data, $this->png());

        // Assert
        $product = Product::where('name', 'Croissant')->first();
        $this->assertSame(5, $product->min_stock);
        $this->assertFalse($product->is_active);
    }

    /** TC-03: tanpa foto -> ValidationException (image wajib). */
    public function test_tc03_store_tanpa_foto_ditolak(): void
    {
        // Act & Assert
        $this->assertStoreFails($this->validData(), null, 'image');
    }

    /** TC-04: file bukan gambar -> ditolak. */
    public function test_tc04_store_file_bukan_gambar_ditolak(): void
    {
        // Arrange
        $pdf = UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf');

        // Act & Assert
        $this->assertStoreFails($this->validData(), $pdf, 'image');
    }

    /** TC-05..: tiap aturan validasi menolak input salah. */
    #[DataProvider('invalidProvider')]
    public function test_tc05_input_tidak_valid_ditolak(array $override, string $errorKey): void
    {
        // Arrange
        Storage::fake('public');

        // Act & Assert
        $this->assertStoreFails($this->validData($override), $this->png(), $errorKey);
        $this->assertSame(0, Product::count());
    }

    public static function invalidProvider(): array
    {
        return [
            'nama kosong'         => [['name' => ''], 'name'],
            'deskripsi kosong'    => [['description' => ''], 'description'],
            'harga negatif'       => [['price' => -1], 'price'],
            'harga bukan angka'   => [['price' => 'abc'], 'price'],
            'stok desimal'        => [['stock' => 1.5], 'stock'],
            'stok negatif'        => [['stock' => -3], 'stock'],
            'kategori tak ada'    => [['category_id' => 99999], 'category_id'],
        ];
    }

    /** TC-06: update dengan foto baru + stok berubah -> foto lama dihapus, riwayat stok tercatat. */
    public function test_tc06_update_ganti_foto_dan_stok_mencatat_riwayat(): void
    {
        // Arrange
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        Storage::disk('public')->put('products/lama.png', 'x');
        $product = $this->makeProduct(['stock' => 20, 'image' => 'products/lama.png']);

        // Act
        $response = $this->callUpdate($product, [
            'category_id' => $product->category_id,
            'name'        => 'Roti Baru',
            'description' => 'Deskripsi baru',
            'price'       => 20000,
            'stock'       => 12,
            'min_stock'   => 4,
            'is_active'   => 1,
        ], $this->png('baru.png'));

        // Assert
        $product->refresh();
        Storage::disk('public')->assertMissing('products/lama.png');
        Storage::disk('public')->assertExists($product->image);
        $this->assertNotSame('products/lama.png', $product->image);
        $this->assertSame('Roti Baru', $product->name);

        $log = StockAdjustment::where('product_id', $product->id)->first();
        $this->assertSame('reduce', $log->type);
        $this->assertSame(8, $log->quantity);
        $this->assertSame(20, $log->stock_before);
        $this->assertSame(12, $log->stock_after);
        $this->assertSame('Produk berhasil diperbarui!', $response->getSession()->get('success'));
    }

    /** TC-07: update tanpa foto & stok sama -> foto tetap, tidak ada riwayat stok. */
    public function test_tc07_update_tanpa_perubahan_stok_tidak_mencatat_riwayat(): void
    {
        // Arrange
        $this->actingAs($this->makeAdmin());
        $product = $this->makeProduct(['stock' => 20, 'image' => 'images/roti1.png']);

        // Act
        $this->callUpdate($product, [
            'category_id' => $product->category_id,
            'name'        => $product->name,
            'description' => 'Deskripsi',
            'price'       => 15000,
            'stock'       => 20,
        ]);

        // Assert
        $this->assertSame('images/roti1.png', $product->fresh()->image);
        $this->assertSame(0, StockAdjustment::count());
    }

    /** TC-08: update menambah stok -> riwayat bertipe "add". */
    public function test_tc08_update_menambah_stok_mencatat_tipe_add(): void
    {
        // Arrange
        $this->actingAs($this->makeAdmin());
        $product = $this->makeProduct(['stock' => 5]);

        // Act
        $this->callUpdate($product, [
            'category_id' => $product->category_id,
            'name'        => $product->name,
            'description' => 'Deskripsi',
            'price'       => 15000,
            'stock'       => 9,
        ]);

        // Assert
        $this->assertSame('add', StockAdjustment::first()->type);
        $this->assertSame(4, StockAdjustment::first()->quantity);
    }

    /** TC-09: hapus produk tanpa riwayat pesanan -> terhapus + file dihapus (MOCK). */
    public function test_tc09_destroy_produk_tanpa_riwayat_menghapus_data_dan_file(): void
    {
        // Arrange
        $product = $this->makeProduct(['image' => 'products/hapus.png']);
        Storage::shouldReceive('disk->delete')->once()->with('products/hapus.png')->andReturn(true); // MOCK

        // Act
        $response = app(ProductController::class)->destroy($product);

        // Assert
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertSame('Produk berhasil dihapus!', $response->getSession()->get('success'));
    }

    /** TC-10: hapus produk yang pernah dipesan -> ditolak, data & file aman. */
    public function test_tc10_destroy_produk_pernah_dipesan_ditolak(): void
    {
        // Arrange
        $product = $this->makeProduct(['image' => 'products/aman.png']);
        $this->makeOrder($this->makeCustomer(), 'paid', [[$product, 2]]);
        Storage::shouldReceive('disk')->never();

        // Act
        $response = app(ProductController::class)->destroy($product);

        // Assert
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertStringContainsString('sudah pernah dipesan', $response->getSession()->get('error'));
    }

    /** TC-11: toggleActive membalik status aktif <-> nonaktif. */
    public function test_tc11_toggle_active_membalik_status(): void
    {
        // Arrange
        $product = $this->makeProduct(['name' => 'Donat', 'is_active' => true]);

        // Act
        $controller = app(ProductController::class);

        // Assert
        $off = $controller->toggleActive($product);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('Produk Donat berhasil dinonaktifkan.', $off->getSession()->get('success'));

        $on = $controller->toggleActive($product->fresh());
        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame('Produk Donat berhasil diaktifkan.', $on->getSession()->get('success'));
    }

    /** TC-12: index, create, edit mengembalikan view yang benar beserta datanya. */
    public function test_tc12_index_create_edit_mengembalikan_view(): void
    {
        // Arrange
        $product = $this->makeProduct();

        // Act
        $controller = app(ProductController::class);

        // Assert
        $index = $controller->index();
        $this->assertSame('admin.products.index', $index->name());
        $this->assertCount(1, $index->getData()['products']);

        $create = $controller->create();
        $this->assertSame('admin.products.create', $create->name());
        $this->assertArrayHasKey('categories', $create->getData());

        $edit = $controller->edit($product);
        $this->assertSame('admin.products.edit', $edit->name());
        $this->assertSame($product->id, $edit->getData()['product']->id);
    }

    private function assertStoreFails(array $data, ?UploadedFile $image, string $key): void
    {
        try {
            $this->callStore($data, $image);
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
