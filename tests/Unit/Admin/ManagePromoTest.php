<?php

namespace Tests\Unit\Admin;

use App\Http\Controllers\Admin\PromoController;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * White-box test fitur MANAGE PROMO
 * Unit: PromoController (index, create, store, edit, update, toggle, validated)
 *
 * Test Double:
 *  - FAKE : SQLite in-memory menggantikan MySQL (data promo nyata, tapi sementara)
 *  - MOCK : Promo::update() dipantau pada toggle() (dipanggil sekali dengan argumen yang benar)
 */
class ManagePromoTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $override = []): array
    {
        return array_merge([
            'code'         => 'hemat10',
            'type'         => 'percent',
            'value'        => 10,
            'min_purchase' => 50000,
            'start_date'   => '2026-12-01',
            'end_date'     => '2026-12-31',
            'quota'        => 100,
            'is_active'    => 1,
        ], $override);
    }

    private function callStore(array $data)
    {
        return app(PromoController::class)->store(Request::create('/admin/promos', 'POST', $data));
    }

    private function callUpdate(Promo $promo, array $data)
    {
        return app(PromoController::class)->update(Request::create('/admin/promos/' . $promo->id, 'PUT', $data), $promo);
    }

    private function makePromo(array $override = []): Promo
    {
        return Promo::create(array_merge([
            'code'         => 'LAMA20',
            'type'         => 'fixed',
            'value'        => 5000,
            'min_purchase' => 0,
            'start_date'   => '2026-11-01',
            'end_date'     => '2026-11-30',
            'quota'        => 10,
            'is_active'    => true,
        ], $override));
    }

    /** TC-01: data valid -> promo tersimpan, kode otomatis huruf besar. */
    public function test_tc01_store_valid_menyimpan_promo_dengan_kode_huruf_besar(): void
    {
        // Act
        $response = $this->callStore($this->validData(['code' => '  hemat10  ']));

        // Assert
        $this->assertDatabaseHas('promos', ['code' => 'HEMAT10', 'type' => 'percent', 'quota' => 100]);
        $this->assertStringEndsWith('/admin/promos', $response->getTargetUrl());
        $this->assertSame('Promo berhasil dibuat!', $response->getSession()->get('success'));
    }

    /** TC-02: min_purchase & is_active tidak dikirim -> min_purchase 0, promo nonaktif. */
    public function test_tc02_default_min_purchase_nol_dan_nonaktif(): void
    {
        // Arrange
        $data = $this->validData();
        unset($data['min_purchase'], $data['is_active']);

        // Act
        $this->callStore($data);

        // Assert
        $promo = Promo::where('code', 'HEMAT10')->first();
        $this->assertEquals(0, $promo->min_purchase);
        $this->assertFalse($promo->is_active);
    }

    /** TC-03..: tiap aturan validasi menolak input salah. */
    #[DataProvider('invalidProvider')]
    public function test_tc03_input_tidak_valid_ditolak(array $override, string $errorKey): void
    {
        // Act & Assert
        try {
            $this->callStore($this->validData($override));
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->errors());
        }

        $this->assertSame(0, Promo::count());
    }

    public static function invalidProvider(): array
    {
        return [
            'kode kosong'                 => [['code' => ''], 'code'],
            'kode berisi spasi'           => [['code' => 'AB CD'], 'code'],
            'tipe tidak dikenal'          => [['type' => 'bonus'], 'type'],
            'nilai nol'                   => [['value' => 0], 'value'],
            'nilai bukan angka'           => [['value' => 'abc'], 'value'],
            'persen lebih dari 100'       => [['value' => 150], 'value'],
            'min belanja negatif'         => [['min_purchase' => -1], 'min_purchase'],
            'tanggal mulai kosong'        => [['start_date' => ''], 'start_date'],
            'tanggal akhir sebelum mulai' => [['end_date' => '2026-11-01'], 'end_date'],
            'kuota nol'                   => [['quota' => 0], 'quota'],
            'kuota desimal'               => [['quota' => 2.5], 'quota'],
        ];
    }

    /** TC-04: nominal tetap boleh > 100 (batas 100 hanya untuk persen). */
    public function test_tc04_tipe_fixed_boleh_nilai_besar(): void
    {
        // Act
        $this->callStore($this->validData(['type' => 'fixed', 'value' => 25000, 'code' => 'POTONG25K']));

        // Assert
        $this->assertDatabaseHas('promos', ['code' => 'POTONG25K', 'type' => 'fixed']);
    }

    /** TC-05: kode duplikat ditolak. */
    public function test_tc05_kode_duplikat_ditolak(): void
    {
        // Arrange
        $this->makePromo(['code' => 'HEMAT10']);

        // Act & Assert
        try {
            $this->callStore($this->validData(['code' => 'hemat10']));
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        $this->assertSame(1, Promo::count());
    }

    /** TC-06: update dengan kode sendiri (tidak berubah) tetap lolos & nilai berubah. */
    public function test_tc06_update_dengan_kode_sendiri_lolos(): void
    {
        // Arrange
        $promo = $this->makePromo(['code' => 'HEMAT10', 'type' => 'percent', 'value' => 10]);

        // Act
        $response = $this->callUpdate($promo, $this->validData(['code' => 'hemat10', 'value' => 20]));

        // Assert
        $this->assertEquals(20, $promo->fresh()->value);
        $this->assertSame('Promo berhasil diperbarui!', $response->getSession()->get('success'));
    }

    /** TC-07: update memakai kode milik promo lain -> ditolak. */
    public function test_tc07_update_dengan_kode_promo_lain_ditolak(): void
    {
        // Arrange
        $this->makePromo(['code' => 'SUDAHADA']);
        $promo = $this->makePromo(['code' => 'PROMOKU']);

        $this->expectException(ValidationException::class);

        // Act
        $this->callUpdate($promo, $this->validData(['code' => 'sudahada']));
    }

    /** TC-08: toggle memanggil update() sekali dengan nilai terbalik (MOCK) dan pesan "dinonaktifkan". */
    public function test_tc08_toggle_memanggil_update_dengan_nilai_terbalik(): void
    {
        // Arrange
        $promo = Mockery::mock(Promo::class)->makePartial();
        $promo->forceFill(['code' => 'HEMAT10', 'is_active' => true]);
        $promo->shouldReceive('update')->once()->with(['is_active' => false])
            ->andReturnUsing(function (array $attrs) use ($promo) {      // MOCK
                $promo->is_active = $attrs['is_active'];

                return true;
            });

        // Act
        $response = app(PromoController::class)->toggle($promo);

        // Assert
        $this->assertSame('Promo HEMAT10 berhasil dinonaktifkan.', $response->getSession()->get('success'));
    }

    /** TC-09: toggle sungguhan pada DB palsu: nonaktif -> aktif. */
    public function test_tc09_toggle_nonaktif_menjadi_aktif(): void
    {
        // Arrange
        $promo = $this->makePromo(['code' => 'HEMAT10', 'is_active' => false]);

        // Act
        $response = app(PromoController::class)->toggle($promo);

        // Assert
        $this->assertTrue($promo->fresh()->is_active);
        $this->assertSame('Promo HEMAT10 berhasil diaktifkan.', $response->getSession()->get('success'));
    }

    /** TC-10: index, create, edit mengembalikan view yang benar. */
    public function test_tc10_index_create_edit_mengembalikan_view(): void
    {
        // Arrange
        $promo = $this->makePromo();

        // Act
        $controller = app(PromoController::class);

        // Assert
        $index = $controller->index();
        $this->assertSame('admin.promos.index', $index->name());
        $this->assertCount(1, $index->getData()['promos']);

        $this->assertSame('admin.promos.create', $controller->create()->name());

        $edit = $controller->edit($promo);
        $this->assertSame('admin.promos.edit', $edit->name());
        $this->assertSame($promo->id, $edit->getData()['promo']->id);
    }
}
