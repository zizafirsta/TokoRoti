<?php

namespace Tests\Unit\Admin;

use App\Http\Controllers\Admin\OrderController;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * White-box test fitur UPDATE ORDER STATUS
 * Unit: Admin\OrderController::update dan Order::nextStatus/canTransitionTo/changeStatus
 *
 * Test Double:
 *  - STUB : Order::canTransitionTo() / nextStatus() dikunci mengembalikan nilai tetap
 *  - MOCK : Order::changeStatus() wajib dipanggil sekali (atau tidak sama sekali)
 *  - FAKE : SQLite in-memory untuk pengujian Order sungguhan
 */
class UpdateOrderStatusTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    private function req(array $data): Request
    {
        return Request::create('/admin/orders/1', 'PATCH', $data);
    }

    private function fakeOrder(string $status = 'pending'): \Mockery\LegacyMockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->forceFill(['status' => $status, 'fulfillment_type' => 'pickup']);

        return $order;
    }

    /** TC-01: transisi valid -> changeStatus dipanggil sekali, flash sukses. */
    public function test_tc01_transisi_valid_memanggil_changeStatus(): void
    {
        // Arrange
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $order = $this->fakeOrder('pending');
        $order->shouldReceive('canTransitionTo')->with('paid')->andReturn(true);          // STUB
        $order->shouldReceive('changeStatus')->once()->with('paid', $admin->id, null)->andReturn(true); // MOCK

        // Act
        $response = app(OrderController::class)->update($this->req(['status' => 'paid']), $order);

        // Assert
        $this->assertSame('Status transaksi berhasil diperbarui!', $response->getSession()->get('success'));
    }

    /** TC-02: loncat urutan -> ditolak, pesan menyebut status berikutnya. */
    public function test_tc02_loncat_urutan_ditolak_dengan_petunjuk_status_berikutnya(): void
    {
        // Arrange
        $order = $this->fakeOrder('pending');
        $order->shouldReceive('canTransitionTo')->andReturn(false);
        $order->shouldReceive('nextStatus')->andReturn('paid');
        $order->shouldReceive('changeStatus')->never();

        // Act
        $response = app(OrderController::class)->update($this->req(['status' => 'completed']), $order);

        // Assert
        $error = $response->getSession()->get('error');
        $this->assertStringContainsString('tidak sesuai urutan', $error);
        $this->assertStringContainsString('Dibayar atau Dibatalkan', $error);
    }

    /** TC-03: pesanan sudah status akhir -> pesan "status akhir". */
    public function test_tc03_pesanan_status_akhir_tidak_bisa_diubah(): void
    {
        // Arrange
        $order = $this->fakeOrder('completed');
        $order->shouldReceive('canTransitionTo')->andReturn(false);
        $order->shouldReceive('nextStatus')->andReturn(null);
        $order->shouldReceive('changeStatus')->never();

        // Act
        $response = app(OrderController::class)->update($this->req(['status' => 'paid']), $order);

        // Assert
        $this->assertStringContainsString('status akhir', $response->getSession()->get('error'));
    }

    /** TC-04: batal dengan alasan -> changeStatus dipanggil dengan alasan itu. */
    public function test_tc04_pembatalan_dengan_alasan_diteruskan_ke_model(): void
    {
        // Arrange
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $order = $this->fakeOrder('paid');
        $order->shouldReceive('canTransitionTo')->with('cancelled')->andReturn(true);
        $order->shouldReceive('changeStatus')->once()->with('cancelled', $admin->id, 'Stok bahan habis')->andReturn(true);

        // Act
        app(OrderController::class)->update(
            $this->req(['status' => 'cancelled', 'cancel_reason' => 'Stok bahan habis']),
            $order
        );
    }

    /** TC-05: batal tanpa alasan -> ValidationException, changeStatus tidak dipanggil. */
    public function test_tc05_pembatalan_tanpa_alasan_ditolak(): void
    {
        // Arrange
        $order = $this->fakeOrder('paid');
        $order->shouldReceive('changeStatus')->never();

        $this->expectException(ValidationException::class);

        // Act
        app(OrderController::class)->update($this->req(['status' => 'cancelled']), $order);
    }

    /** TC-06: status tidak dikenal / kosong -> ValidationException. */
    public function test_tc06_status_tidak_valid_ditolak(): void
    {
        // Arrange
        $order = $this->fakeOrder('pending');
        $order->shouldReceive('changeStatus')->never();

        // Act & Assert
        try {
            app(OrderController::class)->update($this->req(['status' => 'terbang']), $order);
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    /** TC-07: Order::nextStatus() untuk semua status & tipe pemenuhan. */
    #[DataProvider('nextStatusProvider')]
    public function test_tc07_next_status(string $status, string $fulfillment, ?string $expected): void
    {
        // Arrange
        $order = new Order(['status' => $status, 'fulfillment_type' => $fulfillment]);

        // Act & Assert
        $this->assertSame($expected, $order->nextStatus());
    }

    public static function nextStatusProvider(): array
    {
        return [
            'pending -> paid'                => ['pending', 'pickup', 'paid'],
            'paid -> processing'             => ['paid', 'pickup', 'processing'],
            'processing pickup -> ready'     => ['processing', 'pickup', 'ready'],
            'processing delivery -> shipped' => ['processing', 'delivery', 'shipped'],
            'ready -> completed'             => ['ready', 'pickup', 'completed'],
            'shipped -> completed'           => ['shipped', 'delivery', 'completed'],
            'completed -> null'              => ['completed', 'pickup', null],
            'cancelled -> null'              => ['cancelled', 'pickup', null],
        ];
    }

    /** TC-08: canTransitionTo() & canBeCancelled(). */
    public function test_tc08_can_transition_to(): void
    {
        // Arrange
        $pending   = new Order(['status' => 'pending', 'fulfillment_type' => 'pickup']);
        $completed = new Order(['status' => 'completed', 'fulfillment_type' => 'pickup']);

        // Act & Assert
        $this->assertTrue($pending->canTransitionTo('paid'));
        $this->assertFalse($pending->canTransitionTo('processing'));
        $this->assertTrue($pending->canTransitionTo('cancelled'));
        $this->assertTrue($pending->canBeCancelled());
        $this->assertFalse($completed->canTransitionTo('cancelled'));
        $this->assertFalse($completed->canBeCancelled());
    }

    /** TC-09: changeStatus sungguhan -> status berubah dan tercatat di order_status_logs. */
    public function test_tc09_change_status_mencatat_log(): void
    {
        // Arrange
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        // Act
        $result = $order->changeStatus('paid', $admin->id);

        // Assert
        $this->assertTrue($result);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id'   => $order->id,
            'status'     => 'paid',
            'changed_by' => $admin->id,
        ]);
    }

    /** TC-10: status sama -> false, tidak ada log baru. */
    public function test_tc10_change_status_sama_mengembalikan_false(): void
    {
        // Arrange
        $order = $this->makeOrder($this->makeCustomer(), 'paid');

        // Act & Assert
        $this->assertFalse($order->changeStatus('paid'));
        $this->assertDatabaseCount('order_status_logs', 0);
    }

    /** TC-11: batal -> stok produk dikembalikan + alasan tercatat. */
    public function test_tc11_pembatalan_mengembalikan_stok(): void
    {
        // Arrange
        $product = $this->makeProduct(['stock' => 20]);
        $order = $this->makeOrder($this->makeCustomer(), 'paid', [[$product, 3]]);

        // Act
        $order->changeStatus('cancelled', null, 'Pelanggan batal');

        // Assert
        $this->assertSame(23, $product->fresh()->stock);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'status'   => 'cancelled',
            'reason'   => 'Pelanggan batal',
        ]);
    }

    /** TC-12: index & show mengembalikan view yang benar. */
    public function test_tc12_index_dan_show_mengembalikan_view(): void
    {
        // Arrange
        $order = $this->makeOrder($this->makeCustomer(), 'pending');

        // Act
        $controller = app(OrderController::class);

        // Assert
        $this->assertSame('admin.orders.index', $controller->index()->name());

        $show = $controller->show($order);
        $this->assertSame('admin.orders.show', $show->name());
        $this->assertSame($order->id, $show->getData()['order']->id);
    }
}
