<?php

namespace Tests\Feature\Requirements;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-006 Update Order Status (Admin): RQ-021 s/d RQ-024 */
class UpdateOrderStatusRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    private function setStatus($admin, Order $order, string $status, array $extra = [])
    {
        return $this->actingAs($admin)->put("/admin/orders/{$order->id}", ['status' => $status] + $extra);
    }

    /** RQ-021: alur pickup. */
    public function test_rq021_pickup_order_follows_full_sequence(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending', [], ['fulfillment_type' => 'pickup']);

        foreach (['paid', 'processing', 'ready', 'completed'] as $status) {
            $this->setStatus($admin, $order, $status)->assertSessionHas('success');
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    /** RQ-021: alur delivery memakai "Dikirim". */
    public function test_rq021_delivery_order_follows_full_sequence(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending', [], ['fulfillment_type' => 'delivery']);

        foreach (['paid', 'processing', 'shipped', 'completed'] as $status) {
            $this->setStatus($admin, $order, $status)->assertSessionHas('success');
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    /** RQ-022: melompati langkah ditolak. */
    public function test_rq022_skipping_a_step_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        $this->setStatus($admin, $order, 'processing')->assertSessionHas('error');
        $this->setStatus($admin, $order, 'completed')->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('order_status_logs', 0);
    }

    /** RQ-022: mundur ke status sebelumnya ditolak. */
    public function test_rq022_going_backwards_is_rejected(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), 'processing');

        $this->setStatus($this->makeAdmin(), $order, 'paid')->assertSessionHas('error');

        $this->assertSame('processing', $order->fresh()->status);
    }

    /** RQ-022: pickup tidak boleh "Dikirim", delivery tidak boleh "Siap Diambil". */
    public function test_rq022_wrong_fulfillment_branch_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $pickup = $this->makeOrder($this->makeCustomer(), 'processing', [], ['fulfillment_type' => 'pickup']);
        $delivery = $this->makeOrder($this->makeCustomer(), 'processing', [], ['fulfillment_type' => 'delivery']);

        $this->setStatus($admin, $pickup, 'shipped')->assertSessionHas('error');
        $this->setStatus($admin, $delivery, 'ready')->assertSessionHas('error');

        $this->assertSame('processing', $pickup->fresh()->status);
        $this->assertSame('processing', $delivery->fresh()->status);
    }

    /** RQ-022: pesanan selesai / dibatalkan tidak bisa diubah lagi. */
    public function test_rq022_final_statuses_cannot_be_changed(): void
    {
        $admin = $this->makeAdmin();
        $done = $this->makeOrder($this->makeCustomer(), 'completed');
        $cancelled = $this->makeOrder($this->makeCustomer(), 'cancelled');

        $this->setStatus($admin, $done, 'cancelled', ['cancel_reason' => 'Salah input'])->assertSessionHas('error');
        $this->setStatus($admin, $cancelled, 'pending')->assertSessionHas('error');

        $this->assertSame('completed', $done->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    /** RQ-022: status yang tidak dikenal ditolak validasi. */
    public function test_rq022_unknown_status_is_rejected(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        $this->setStatus($this->makeAdmin(), $order, 'failed')->assertSessionHasErrors('status');
    }

    /** RQ-022 (white-box, model): nextStatus() & canTransitionTo(). */
    public function test_rq022_model_transition_rules(): void
    {
        $o = new Order(['status' => 'pending', 'fulfillment_type' => 'pickup']);
        $this->assertSame('paid', $o->nextStatus());
        $this->assertTrue($o->canTransitionTo('paid'));
        $this->assertTrue($o->canTransitionTo('cancelled'));
        $this->assertFalse($o->canTransitionTo('completed'));

        $o->status = 'processing';
        $this->assertSame('ready', $o->nextStatus());
        $o->fulfillment_type = 'delivery';
        $this->assertSame('shipped', $o->nextStatus());

        $o->status = 'completed';
        $this->assertNull($o->nextStatus());
        $this->assertFalse($o->canBeCancelled());
    }

    /** RQ-023: pembatalan tanpa alasan ditolak. */
    public function test_rq023_cancellation_requires_reason(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), 'paid');

        $this->setStatus($this->makeAdmin(), $order, 'cancelled', ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertSame('paid', $order->fresh()->status);
    }

    /** RQ-023: pembatalan beralasan berhasil, alasan tercatat, stok dikembalikan. */
    public function test_rq023_cancellation_with_reason_succeeds_and_restocks(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(['stock' => 10]);
        $order = $this->makeOrder($this->makeCustomer(), 'paid', [[$product, 2]]);

        $this->setStatus($admin, $order, 'cancelled', ['cancel_reason' => 'Pelanggan membatalkan'])->assertSessionHas('success');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id, 'status' => 'cancelled', 'reason' => 'Pelanggan membatalkan', 'changed_by' => $admin->id,
        ]);
    }

    /** RQ-024: setiap perubahan status tercatat berurutan beserta admin pengubahnya. */
    public function test_rq024_every_status_change_is_logged(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        foreach (['paid', 'processing', 'ready', 'completed'] as $status) {
            $this->setStatus($admin, $order, $status);
        }

        $logs = $order->statusLogs()->orderBy('id')->get();
        $this->assertSame(['paid', 'processing', 'ready', 'completed'], $logs->pluck('status')->all());
        $this->assertTrue($logs->every(fn ($log) => $log->changed_by === $admin->id));
        $this->assertNotNull($logs->first()->created_at);
    }

    /** RQ-024: riwayat tampil di halaman detail pesanan admin. */
    public function test_rq024_history_is_visible_on_order_detail_page(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'paid');
        $this->setStatus($admin, $order, 'cancelled', ['cancel_reason' => 'Stok bahan habis']);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Riwayat Status')
            ->assertSee('Stok bahan habis');
    }
}
