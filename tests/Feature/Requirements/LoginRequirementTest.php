<?php

namespace Tests\Feature\Requirements;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** FS-001 Login: RQ-001 s/d RQ-004 */
class LoginRequirementTest extends TestCase
{
    use RefreshDatabase, BuildsFixtures;

    /** RQ-001: kredensial valid -> terautentikasi dan admin diarahkan ke dashboard admin. */
    public function test_rq001_admin_login_valid_redirects_to_admin_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    /** RQ-001: customer dengan kredensial valid tetap masuk ke dashboard customer. */
    public function test_rq001_customer_login_valid_redirects_to_customer_dashboard(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($customer);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /** RQ-002: password salah -> pesan error ditampilkan dan tidak login. */
    public function test_rq002_wrong_password_shows_error(): void
    {
        $user = $this->makeCustomer();

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'salah-total']);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    /** RQ-002: email tidak terdaftar -> pesan error yang sama. */
    public function test_rq002_unknown_email_shows_error(): void
    {
        $response = $this->from('/login')->post('/login', ['email' => 'tidakada@example.com', 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    /** RQ-003: 5x gagal berturut-turut -> akun terkunci, bahkan password benar ditolak. */
    public function test_rq003_account_locked_after_five_failed_attempts(): void
    {
        $user = $this->makeCustomer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
    }

    /** RQ-003 (batas bawah): 4x gagal belum mengunci, login valid masih berhasil. */
    public function test_rq003_four_failed_attempts_do_not_lock(): void
    {
        $user = $this->makeCustomer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    /** RQ-004: tanpa login -> diarahkan ke halaman login. */
    public function test_rq004_guest_cannot_access_admin_pages(): void
    {
        foreach (['/admin/dashboard', '/admin/products', '/admin/orders', '/admin/stock', '/admin/promos', '/admin/reports/sales'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    /** RQ-004: akun customer -> 403 Forbidden. */
    public function test_rq004_customer_cannot_access_admin_pages(): void
    {
        $customer = $this->makeCustomer();

        foreach (['/admin/dashboard', '/admin/products', '/admin/orders', '/admin/stock', '/admin/promos', '/admin/reports/sales'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }

    /** RQ-004 (pembanding): admin dapat mengakses halaman admin. */
    public function test_rq004_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())->get('/admin/dashboard')->assertOk();
    }
}
