<?php

namespace Tests\Feature\Requirements;

use App\Models\Order;
use App\Services\SalesReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-008 View Sales Report (Admin): RQ-029 s/d RQ-031 */
class SalesReportRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    private $productA;
    private $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = $this->makeCustomer();
        $this->productA = $this->makeProduct(['name' => 'Croissant', 'price' => 20000]);
        $this->productB = $this->makeProduct(['name' => 'Sourdough', 'price' => 40000]);

        // Masuk laporan (dalam rentang 7 hari terakhir)
        $this->makeOrder($customer, 'completed', [[$this->productA, 3], [$this->productB, 1]], ['total' => 100000, 'created_at' => now()->subDays(2)]);
        $this->makeOrder($customer, 'paid', [[$this->productA, 2]], ['total' => 50000, 'created_at' => now()->subDay()]);
        // TIDAK masuk: dibatalkan, belum dibayar, di luar rentang
        $this->makeOrder($customer, 'cancelled', [[$this->productA, 10]], ['total' => 999000, 'created_at' => now()->subDay()]);
        $this->makeOrder($customer, 'pending', [[$this->productB, 9]], ['total' => 888000, 'created_at' => now()->subDay()]);
        $this->makeOrder($customer, 'completed', [[$this->productB, 7]], ['total' => 70000, 'created_at' => now()->subDays(30)]);
    }

    private function range(): array
    {
        return [now()->subDays(7)->toDateString(), now()->toDateString()];
    }

    /** RQ-029: total, jumlah order, produk terlaris (service). */
    public function test_rq029_report_contains_total_count_and_top_products(): void
    {
        [$from, $to] = $this->range();

        $report = SalesReport::generate(Carbon::parse($from), Carbon::parse($to));

        $this->assertEquals(150000, $report['total_sales']);
        $this->assertSame(2, $report['order_count']);
        $this->assertFalse($report['is_empty']);
        $this->assertSame('Croissant', $report['top_products'][0]->name);
        $this->assertEquals(5, $report['top_products'][0]->qty_sold);
        $this->assertSame('Sourdough', $report['top_products'][1]->name);
        $this->assertEquals(1, $report['top_products'][1]->qty_sold);
    }

    /** RQ-029: halaman laporan menampilkan ketiganya. */
    public function test_rq029_report_page_displays_figures(): void
    {
        [$from, $to] = $this->range();

        $this->actingAs($this->makeAdmin())->get("/admin/reports/sales?start_date={$from}&end_date={$to}")
            ->assertOk()
            ->assertSee('Rp 150.000')
            ->assertSee('Produk Terlaris')
            ->assertSee('Croissant')
            ->assertSee('Sourdough');
    }

    /** RQ-029: rentang tanggal mempengaruhi hasil (rentang 60 hari ikut memuat order 30 hari lalu). */
    public function test_rq029_date_range_changes_results(): void
    {
        $report = SalesReport::generate(now()->subDays(60), now());

        $this->assertEquals(220000, $report['total_sales']); // 100k + 50k + 70k
        $this->assertSame(3, $report['order_count']);
    }

    /** RQ-029 (batas): order pada tanggal awal dan akhir ikut terhitung (inklusif). */
    public function test_rq029_range_is_inclusive_of_boundary_days(): void
    {
        Order::query()->delete();
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, 'completed', [], ['total' => 10000, 'created_at' => Carbon::parse('2026-03-01 00:00:00')]);
        $this->makeOrder($customer, 'completed', [], ['total' => 20000, 'created_at' => Carbon::parse('2026-03-31 23:59:59')]);
        $this->makeOrder($customer, 'completed', [], ['total' => 40000, 'created_at' => Carbon::parse('2026-04-01 00:00:00')]);

        $report = SalesReport::generate(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        $this->assertEquals(30000, $report['total_sales']);
        $this->assertSame(2, $report['order_count']);
    }

    /** RQ-030: total laporan = jumlah total pesanan pada rentang & status penjualan yang sama. */
    public function test_rq030_report_total_matches_order_data(): void
    {
        [$from, $to] = $this->range();

        $report = SalesReport::generate(Carbon::parse($from), Carbon::parse($to));

        $expected = Order::whereIn('status', Order::SALES_STATUSES)
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->sum('total');

        $this->assertEquals($expected, $report['total_sales']);
        $this->assertEquals(
            Order::whereIn('status', Order::SALES_STATUSES)->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])->count(),
            $report['order_count']
        );
    }

    /** RQ-030: pesanan dibatalkan / belum dibayar tidak menggelembungkan total. */
    public function test_rq030_cancelled_and_pending_orders_are_excluded(): void
    {
        [$from, $to] = $this->range();

        $report = SalesReport::generate(Carbon::parse($from), Carbon::parse($to));

        $this->assertLessThan(999000, $report['total_sales']);
        $this->assertSame(2, $report['order_count']);
    }

    /** RQ-031: tidak ada data -> pesan khusus. */
    public function test_rq031_empty_range_shows_special_message(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get('/admin/reports/sales?start_date=2020-01-01&end_date=2020-01-31');

        $response->assertOk()
            ->assertSee('Tidak ada data penjualan pada rentang tanggal yang dipilih')
            ->assertDontSee('Produk Terlaris');
    }

    /** RQ-031 (service): flag is_empty. */
    public function test_rq031_service_flags_empty_report(): void
    {
        $report = SalesReport::generate(Carbon::parse('2020-01-01'), Carbon::parse('2020-01-31'));

        $this->assertTrue($report['is_empty']);
        $this->assertSame(0, $report['order_count']);
        $this->assertEquals(0, $report['total_sales']);
        $this->assertCount(0, $report['top_products']);
    }

    /** Validasi: tanggal akhir sebelum tanggal mulai ditolak. */
    public function test_report_rejects_end_date_before_start_date(): void
    {
        $this->actingAs($this->makeAdmin())->get('/admin/reports/sales?start_date=2026-05-10&end_date=2026-05-01')
            ->assertSessionHasErrors('end_date');
    }

    /** Akses: customer tidak boleh melihat laporan. */
    public function test_customer_cannot_view_report(): void
    {
        $this->actingAs($this->makeCustomer())->get('/admin/reports/sales')->assertForbidden();
    }
}
