<?php

namespace Tests\Unit;

use App\Http\Controllers\CartController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\LegacyMockInterface;
use Tests\TestCase;

/**
 * White-box test untuk fitur "Tambah ke Keranjang" (CartController::add).
 *
 * Test Double yang dipakai:
 *  - FAKE : database SQLite in-memory (phpunit.xml) menggantikan database asli.
 *  - STUB : Session::get('cart') dikunci agar selalu mengembalikan isi keranjang buatan.
 *  - SPY  : Session::put('cart', ...) direkam, lalu diverifikasi setelah method dijalankan.
 */
class CartAddTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buat STUB + SPY untuk session; $cart = isi keranjang awal yang "dikembalikan".
     * Partial mock dipakai supaya method lain (mis. flash untuk redirect) tetap memakai session asli.
     */
    private function fakeSession(array $cart = []): LegacyMockInterface
    {
        $session = Mockery::mock(SessionManager::class, [$this->app])->makePartial();
        Session::swap($session);
        $session->shouldReceive('get')->with('cart', [])->andReturn($cart); // STUB
        $session->shouldReceive('put')->andReturnNull();                    // SPY (direkam)

        return $session;
    }

    private function makeProduct(int $stock, bool $active = true, string $name = 'Roti Tawar'): Product
    {
        $category = Category::create(['name' => 'Roti', 'slug' => 'roti-' . uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name'        => $name,
            'price'       => 15000,
            'stock'       => $stock,
            'is_active'   => $active,
        ]);
    }

    private function callAdd(int $productId, array $params = [], bool $json = false)
    {
        $request = Request::create('/cart/add/' . $productId, 'POST', $params);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return app(CartController::class)->add($request, $productId);
    }

    /** TC-01: stok produk 0 -> ditolak (JSON 422), keranjang tidak diubah. */
    public function test_tc01_stok_habis_mengembalikan_422_json(): void
    {
        $product = $this->makeProduct(stock: 0);
        $session = $this->fakeSession();

        $response = $this->callAdd($product->id, ['quantity' => 1], json: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['ok']);
        $this->assertStringContainsString('sedang habis', $response->getData(true)['message']);
        $session->shouldNotHaveReceived('put'); // SPY
    }

    /** TC-02: jumlah di keranjang sudah sama dengan stok -> ditolak (JSON 422). */
    public function test_tc02_keranjang_sudah_mencapai_stok_ditolak(): void
    {
        $product = $this->makeProduct(stock: 5);
        $session = $this->fakeSession([
            $product->id => ['name' => 'Roti Tawar', 'quantity' => 5, 'price' => 15000],
        ]);

        $response = $this->callAdd($product->id, ['quantity' => 1], json: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('sudah mencapai stok', $response->getData(true)['message']);
        $session->shouldNotHaveReceived('put'); // SPY
    }

    /** TC-03: penambahan normal (JSON) -> sukses, keranjang disimpan ke session. */
    public function test_tc03_tambah_normal_mengembalikan_json_sukses(): void
    {
        $product = $this->makeProduct(stock: 10);
        $session = $this->fakeSession();

        $response = $this->callAdd($product->id, ['quantity' => 2], json: true);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['ok']);
        $this->assertFalse($data['adjusted']);
        $this->assertSame(2, $data['in_cart']);
        $this->assertSame(30000, $data['cart_total']);

        // SPY: pastikan keranjang yang disimpan benar
        $session->shouldHaveReceived('put')->once()->withArgs(function ($key, $cart) use ($product) {
            return $key === 'cart' && $cart[$product->id]['quantity'] === 2;
        });
    }

    /** TC-04: penambahan normal (non-JSON) -> redirect dengan pesan sukses. */
    public function test_tc04_tambah_normal_non_json_redirect_sukses(): void
    {
        $product = $this->makeProduct(stock: 10);
        $session = $this->fakeSession();

        $response = $this->callAdd($product->id); // tanpa quantity -> default 1

        $this->assertTrue($response->isRedirection());
        $this->assertSame('Roti berhasil ditambahkan ke keranjang!', $response->getSession()->get('success'));
        $session->shouldHaveReceived('put')->once();
    }

    /** TC-05: jumlah diminta melebihi stok -> disesuaikan (adjusted = true). */
    public function test_tc05_jumlah_melebihi_stok_disesuaikan(): void
    {
        $product = $this->makeProduct(stock: 3);
        $session = $this->fakeSession();

        $data = $this->callAdd($product->id, ['quantity' => 5], json: true)->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertTrue($data['adjusted']);
        $this->assertSame(3, $data['in_cart']);
        $session->shouldHaveReceived('put')->once();
    }

    /** TC-06: quantity = 0 (tidak valid) -> ValidationException. */
    public function test_tc06_quantity_tidak_valid_melempar_validation_exception(): void
    {
        $product = $this->makeProduct(stock: 10);
        $session = $this->fakeSession();

        $this->expectException(ValidationException::class);

        try {
            $this->callAdd($product->id, ['quantity' => 0]);
        } finally {
            $session->shouldNotHaveReceived('put');
        }
    }

    /** TC-07: produk nonaktif -> findOrFail melempar ModelNotFoundException (404). */
    public function test_tc07_produk_nonaktif_tidak_ditemukan(): void
    {
        $product = $this->makeProduct(stock: 10, active: false);
        $this->fakeSession();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->callAdd($product->id, ['quantity' => 1]);
    }

    /** TC-08: stok habis (non-JSON) -> redirect dengan pesan error. */
    public function test_tc08_stok_habis_non_json_redirect_error(): void
    {
        $product = $this->makeProduct(stock: 0, name: 'Donat');
        $this->fakeSession();

        $response = $this->callAdd($product->id, ['quantity' => 1]);

        $this->assertTrue($response->isRedirection());
        $this->assertSame('Donat sedang habis.', $response->getSession()->get('error'));
    }
}
