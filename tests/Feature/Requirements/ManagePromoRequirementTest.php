<?php

namespace Tests\Feature\Requirements;

use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-007 Manage Promo (Admin): RQ-025 s/d RQ-028 */
class ManagePromoRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    private function payload(array $override = []): array
    {
        return array_merge([
            'code'         => 'hemat20',
            'type'         => 'percent',
            'value'        => 20,
            'min_purchase' => 50000,
            'start_date'   => now()->toDateString(),
            'end_date'     => now()->addDays(30)->toDateString(),
            'quota'        => 50,
            'is_active'    => 1,
        ], $override);
    }

    private function makePromo(array $override = []): Promo
    {
        return Promo::create(array_merge([
            'code' => 'LAMA10', 'type' => 'fixed', 'value' => 10000, 'min_purchase' => 0,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(10)->toDateString(),
            'quota' => 10, 'is_active' => true,
        ], $override));
    }

    /** RQ-025: promo persen valid (kode disimpan huruf besar). */
    public function test_rq025_admin_can_create_percent_promo(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post('/admin/promos', $this->payload());

        $response->assertRedirect(route('admin.promos.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('promos', ['code' => 'HEMAT20', 'type' => 'percent', 'quota' => 50, 'is_active' => 1]);
    }

    /** RQ-025: promo nominal valid. */
    public function test_rq025_admin_can_create_fixed_promo(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/promos', $this->payload(['code' => 'POTONG15K', 'type' => 'fixed', 'value' => 15000]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('promos', ['code' => 'POTONG15K', 'type' => 'fixed']);
    }

    /** RQ-025: data tidak valid ditolak. */
    public function test_rq025_invalid_promo_data_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/promos', [])->assertSessionHasErrors(['code', 'type', 'value', 'start_date', 'end_date', 'quota']);
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['type' => 'bonus']))->assertSessionHasErrors('type');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['value' => 0]))->assertSessionHasErrors('value');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['value' => -5]))->assertSessionHasErrors('value');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['value' => 150]))->assertSessionHasErrors('value'); // persen > 100
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['min_purchase' => -1]))->assertSessionHasErrors('min_purchase');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['quota' => 0]))->assertSessionHasErrors('quota');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['quota' => 1.5]))->assertSessionHasErrors('quota');
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['start_date' => 'bukan-tanggal']))->assertSessionHasErrors('start_date');
        $this->actingAs($admin)->post('/admin/promos', $this->payload([
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->toDateString(),
        ]))->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('promos', 0);
    }

    /** RQ-025 (batas): nilai nominal boleh > 100, persen tepat 100 boleh. */
    public function test_rq025_value_boundaries(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/promos', $this->payload(['code' => 'FULL100', 'value' => 100]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/promos', $this->payload(['code' => 'FIX500', 'type' => 'fixed', 'value' => 500000]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('promos', 2);
    }

    /** RQ-026: kode sama ditolak (termasuk beda huruf besar/kecil). */
    public function test_rq026_duplicate_code_is_rejected(): void
    {
        $this->makePromo(['code' => 'HEMAT20']);

        $response = $this->actingAs($this->makeAdmin())->post('/admin/promos', $this->payload(['code' => 'hemat20']));

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, Promo::where('code', 'HEMAT20')->count());
    }

    /** RQ-027 */
    public function test_rq027_admin_can_update_promo(): void
    {
        $promo = $this->makePromo();

        $response = $this->actingAs($this->makeAdmin())->put("/admin/promos/{$promo->id}", $this->payload([
            'code' => 'LAMA10', 'type' => 'percent', 'value' => 25, 'quota' => 99,
        ]));

        $response->assertRedirect(route('admin.promos.index'))->assertSessionHas('success');
        $promo->refresh();
        $this->assertSame('percent', $promo->type);
        $this->assertEquals(25, $promo->value);
        $this->assertSame(99, $promo->quota);
    }

    /** RQ-027: mengubah promo tanpa mengganti kode tidak dianggap duplikat; memakai kode promo lain ditolak. */
    public function test_rq027_update_code_uniqueness(): void
    {
        $a = $this->makePromo(['code' => 'AAA']);
        $this->makePromo(['code' => 'BBB']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/admin/promos/{$a->id}", $this->payload(['code' => 'AAA']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/admin/promos/{$a->id}", $this->payload(['code' => 'BBB']))->assertSessionHasErrors('code');
    }

    /** RQ-028 */
    public function test_rq028_admin_can_activate_and_deactivate_promo(): void
    {
        $promo = $this->makePromo(['is_active' => true]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->patch("/admin/promos/{$promo->id}/toggle")->assertSessionHas('success');
        $this->assertFalse($promo->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/promos/{$promo->id}/toggle")->assertSessionHas('success');
        $this->assertTrue($promo->fresh()->is_active);
    }

    /** Akses: customer tidak boleh mengelola promo. */
    public function test_customer_cannot_manage_promos(): void
    {
        $this->actingAs($this->makeCustomer())->post('/admin/promos', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('promos', 0);
    }
}
