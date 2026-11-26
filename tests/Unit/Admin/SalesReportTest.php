<?php

namespace Tests\Unit\Admin;

use App\Http\Controllers\Admin\ReportController;
use App\Services\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * White-box test fitur VIEW SALES REPORT
 * Unit: SalesReport::generate dan Admin\ReportController::sales
 *
 * Test Double:
 *  - FAKE : SQLite in-memory + data order buatan (DB asli tidak disentuh)
 *  - STUB : $this->travelTo() mengunci waktu "sekarang" agar rentang default laporan deterministik
 */
class SalesReportTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    /** Data uji: periode 1-30 November 2026. */
    private function seedOrders(): void
    {
        $customer = $this->makeCustomer();
        $a = $this->makeProduct(['name' => 'Aroti', 'price' => 10000]);
        $b = $this->makeProduct(['name' => 'Broti', 'price' => 20000]);

        // Dihitung sebagai penjualan, di dalam periode
        $this->makeOrder($customer, 'paid', [[$a, 2], [$b, 1]], ['total' => 50000, 'created_at' => Carbon::parse('2026-11-10 12:00:00')]);
        // Tepat di hari terakhir (23:30) -> tetap masuk karena endOfDay
        $this->makeOrder($customer, 'completed', [[$a, 3]], ['total' => 30000, 'created_at' => Carbon::parse('2026-11-30 23:30:00')]);

        // Tidak dihitung: status pending / cancelled
        $this->makeOrder($customer, 'pending', [[$a, 9]], ['total' => 99999, 'created_at' => Carbon::parse('2026-11-12 09:00:00')]);
        $this->makeOrder($customer, 'cancelled', [[$a, 9]], ['total' => 88888, 'created_at' => Carbon::parse('2026-11-12 10:00:00')]);

        // Tidak dihitung: di luar periode
        $this->makeOrder($customer, 'paid', [[$a, 9]], ['total' => 77777, 'created_at' => Carbon::parse('2026-12-01 00:10:00')]);
        $this->makeOrder($customer, 'paid', [[$a, 9]], ['total' => 66666, 'created_at' => Carbon::parse('2026-10-31 23:59:00')]);
    }

    private function november(): array
    {
        return [Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')];
    }

    /** TC-01: hanya status penjualan & dalam periode yang dihitung (total + jumlah order). */
    public function test_tc01_total_dan_jumlah_order_hanya_dari_penjualan_dalam_periode(): void
    {
        // Arrange
        $this->seedOrders();

        // Act
        $report = SalesReport::generate(...$this->november());

        // Assert
        $this->assertSame(2, $report['order_count']);
        $this->assertEquals(80000, $report['total_sales']);
        $this->assertFalse($report['is_empty']);
    }

    /** TC-02: produk terlaris diurutkan menurut qty, dengan qty & revenue yang benar. */
    public function test_tc02_produk_terlaris_terurut_dan_benar(): void
    {
        // Arrange
        $this->seedOrders();

        // Act
        $top = SalesReport::generate(...$this->november())['top_products'];

        // Assert
        $this->assertCount(2, $top);
        $this->assertSame('Aroti', $top[0]->name);
        $this->assertEquals(5, $top[0]->qty_sold);
        $this->assertEquals(50000, $top[0]->revenue);
        $this->assertSame('Broti', $top[1]->name);
        $this->assertEquals(1, $top[1]->qty_sold);
        $this->assertEquals(20000, $top[1]->revenue);
    }

    /** TC-03: tidak ada penjualan -> is_empty true, total 0. */
    public function test_tc03_periode_tanpa_penjualan_menghasilkan_laporan_kosong(): void
    {
        // Act
        $report = SalesReport::generate(...$this->november());

        // Assert
        $this->assertTrue($report['is_empty']);
        $this->assertSame(0, $report['order_count']);
        $this->assertEquals(0, $report['total_sales']);
        $this->assertCount(0, $report['top_products']);
    }

    /** TC-04: parameter $topLimit membatasi jumlah produk terlaris. */
    public function test_tc04_top_limit_membatasi_hasil(): void
    {
        // Arrange
        $this->seedOrders();

        // Act
        $top = SalesReport::generate(...[...$this->november(), 1])['top_products'];

        // Assert
        $this->assertCount(1, $top);
        $this->assertSame('Aroti', $top[0]->name);
    }

    /** TC-05: qty sama -> diurutkan menurut nama produk (A-Z). */
    public function test_tc05_qty_sama_diurutkan_menurut_nama(): void
    {
        // Arrange
        $zebra = $this->makeProduct(['name' => 'Zebra Cake']);
        $alfa  = $this->makeProduct(['name' => 'Alfa Bun']);
        $this->makeOrder($this->makeCustomer(), 'paid', [[$zebra, 2], [$alfa, 2]], ['created_at' => Carbon::parse('2026-11-05 10:00:00')]);

        // Act
        $top = SalesReport::generate(...$this->november())['top_products'];

        // Assert
        $this->assertSame('Alfa Bun', $top[0]->name);
        $this->assertSame('Zebra Cake', $top[1]->name);
    }

    /** TC-06: start & end dinormalisasi ke awal/akhir hari; objek asli tidak berubah. */
    public function test_tc06_rentang_dinormalisasi_awal_dan_akhir_hari(): void
    {
        // Arrange
        [$start, $end] = $this->november();

        // Act
        $report = SalesReport::generate($start, $end);

        // Assert
        $this->assertSame('2026-11-01 00:00:00', $report['start']->toDateTimeString());
        $this->assertSame('2026-11-30 23:59:59', $report['end']->toDateTimeString());
        $this->assertSame('2026-11-01', $start->toDateString());
    }

    /** TC-07: controller tanpa filter -> default awal bulan ini s/d hari ini (waktu di-STUB). */
    public function test_tc07_controller_default_awal_bulan_sampai_hari_ini(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));  // STUB waktu

        // Act
        $view = app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET'));

        // Assert
        $this->assertSame('admin.reports.sales', $view->name());
        $report = $view->getData()['report'];
        $this->assertSame('2026-11-01 00:00:00', $report['start']->toDateTimeString());
        $this->assertSame('2026-11-15 23:59:59', $report['end']->toDateTimeString());
    }

    /** TC-08: controller dengan start & end -> memakai rentang yang dipilih. */
    public function test_tc08_controller_memakai_rentang_pilihan(): void
    {
        // Arrange
        $this->seedOrders();

        // Act
        $view = app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET', [
            'start_date' => '2026-11-01',
            'end_date'   => '2026-11-30',
        ]));

        // Assert
        $report = $view->getData()['report'];
        $this->assertSame(2, $report['order_count']);
        $this->assertEquals(80000, $report['total_sales']);
    }

    /** TC-09: hanya start_date diisi -> end = hari ini (waktu di-STUB). */
    public function test_tc09_hanya_start_date_end_default_hari_ini(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        // Act
        $view = app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET', ['start_date' => '2026-11-05']));

        // Assert
        $report = $view->getData()['report'];
        $this->assertSame('2026-11-05 00:00:00', $report['start']->toDateTimeString());
        $this->assertSame('2026-11-15 23:59:59', $report['end']->toDateTimeString());
    }

    /** TC-10: tanggal akhir < tanggal mulai -> ValidationException. */
    public function test_tc10_tanggal_akhir_sebelum_mulai_ditolak(): void
    {
        // Act & Assert
        try {
            app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET', [
                'start_date' => '2026-11-20',
                'end_date'   => '2026-11-10',
            ]));
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_date', $e->errors());
        }
    }

    /** TC-11: format tanggal salah -> ValidationException. */
    public function test_tc11_format_tanggal_salah_ditolak(): void
    {
        // Act & Assert
        try {
            app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET', ['start_date' => 'bukan-tanggal']));
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_date', $e->errors());
        }
    }

    /**
     * TC-12: hanya end_date lebih awal dari awal bulan ini.
     * Cabang "if ($end->lt($start))" di controller: end disamakan dengan start.
     * Jika validasi Laravel sudah menolaknya, hasilnya ValidationException (juga diterima).
     */
    public function test_tc12_end_date_lebih_awal_dari_awal_bulan(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));

        // Act & Assert
        try {
            $view = app(ReportController::class)->sales(Request::create('/admin/reports/sales', 'GET', ['end_date' => '2026-10-01']));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_date', $e->errors());

            return;
        }

        $report = $view->getData()['report'];
        $this->assertSame($report['start']->toDateString(), $report['end']->toDateString());
    }
}
