<?php

namespace Tests\Feature\Requirements;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-004 Manage Product (Admin): RQ-013 s/d RQ-017. Membutuhkan ekstensi PHP GD (UploadedFile::fake()->image). */
class ManageProductRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function validPayload(array $override = []): array
    {
        return array_merge([
            'name'        => 'Donat Cokelat',
            'category_id' => $this->makeCategory()->id,
            'price'       => 12000,
            'stock'       => 30,
            'min_stock'   => 5,
            'description' => 'Donat empuk dengan topping cokelat.',
            'image'       => UploadedFile::fake()->image('donat.jpg'),
            'is_active'   => 1,
        ], $override);
    }

    /** RQ-013 */
    public function test_rq013_admin_can_add_product_with_valid_data(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post('/admin/products', $this->validPayload());

        $response->assertRedirect(route('admin.products.index'))->assertSessionHas('success');

        $product = Product::where('name', 'Donat Cokelat')->firstOrFail();
        $this->assertEquals(12000, $product->price);
        $this->assertSame(30, $product->stock);
        $this->assertSame('Donat empuk dengan topping cokelat.', $product->description);
        Storage::disk('public')->assertExists($product->image);
    }

    /** RQ-014 */
    public function test_rq014_product_with_empty_required_fields_is_rejected(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post('/admin/products', []);

        $response->assertSessionHasErrors(['name', 'category_id', 'price', 'stock', 'description', 'image']);
        $this->assertDatabaseCount('products', 0);
    }

    /** RQ-014: nilai tidak valid (harga/stok negatif, kategori tidak ada, file bukan gambar). */
    public function test_rq014_invalid_values_are_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/products', $this->validPayload(['price' => -1]))->assertSessionHasErrors('price');
        $this->actingAs($admin)->post('/admin/products', $this->validPayload(['stock' => -5]))->assertSessionHasErrors('stock');
        $this->actingAs($admin)->post('/admin/products', $this->validPayload(['category_id' => 9999]))->assertSessionHasErrors('category_id');
        $this->actingAs($admin)->post('/admin/products', $this->validPayload(['image' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    /** RQ-015 */
    public function test_rq015_admin_can_update_product(): void
    {
        $product = $this->makeProduct(['name' => 'Lama', 'price' => 10000, 'stock' => 10]);

        $response = $this->actingAs($this->makeAdmin())->put("/admin/products/{$product->id}", [
            'name'        => 'Baru',
            'category_id' => $product->category_id,
            'price'       => 25000,
            'stock'       => 10,
            'description' => 'Deskripsi baru',
            'is_active'   => 1,
        ]);

        $response->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
        $product->refresh();
        $this->assertSame('Baru', $product->name);
        $this->assertEquals(25000, $product->price);
        $this->assertSame('Deskripsi baru', $product->description);
    }

    /** RQ-016 */
    public function test_rq016_product_without_order_history_can_be_deleted(): void
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->makeAdmin())->delete("/admin/products/{$product->id}");

        $response->assertRedirect(route('admin.products.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** RQ-017: produk yang pernah dipesan tidak bisa dihapus... */
    public function test_rq017_product_with_order_history_cannot_be_deleted(): void
    {
        $product = $this->makeProduct();
        $this->makeOrder($this->makeCustomer(), 'completed', [[$product, 2]]);

        $response = $this->actingAs($this->makeAdmin())->delete("/admin/products/{$product->id}");

        $response->assertRedirect(route('admin.products.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** RQ-017: ...tetapi bisa dinonaktifkan (dan diaktifkan kembali). */
    public function test_rq017_product_with_order_history_can_be_deactivated(): void
    {
        $product = $this->makeProduct(['is_active' => true]);
        $this->makeOrder($this->makeCustomer(), 'completed', [[$product, 1]]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->patch("/admin/products/{$product->id}/toggle-active")->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/products/{$product->id}/toggle-active");
        $this->assertTrue($product->fresh()->is_active);
    }

    /** RQ-017: produk nonaktif tidak muncul di katalog publik. */
    public function test_rq017_deactivated_product_is_hidden_from_catalog(): void
    {
        $product = $this->makeProduct(['name' => 'Roti Nonaktif', 'is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('Roti Nonaktif');
        $this->get("/product/{$product->id}")->assertNotFound();
    }
}
