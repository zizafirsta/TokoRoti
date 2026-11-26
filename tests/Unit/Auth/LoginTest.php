<?php

namespace Tests\Unit\Auth;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * White-box test fitur LOGIN
 * Unit: LoginRequest (authenticate, ensureIsNotRateLimited, throttleKey, rules)
 *       AuthenticatedSessionController::store
 *
 * Test Double:
 *  - STUB : Auth::attempt() dikunci mengembalikan true/false (tanpa DB & hashing)
 *  - MOCK : RateLimiter::hit() / clear() wajib dipanggil tepat sekali
 *  - FAKE : Event::fake() menggantikan dispatcher event asli (Lockout)
 *  - MOCK : session->regenerate() pada controller store() wajib dipanggil sekali
 */
class LoginTest extends TestCase
{
    private function makeRequest(string $email = 'budi@example.com', string $password = 'rahasia123'): LoginRequest
    {
        return LoginRequest::create('/login', 'POST', ['email' => $email, 'password' => $password]);
    }

    /** TC-01: kredensial salah -> ValidationException + hitungan gagal bertambah (MOCK hit). */
    public function test_tc01_kredensial_salah_melempar_exception_dan_menghitung_gagal(): void
    {
        // Arrange
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
        Auth::shouldReceive('attempt')->once()->andReturn(false);   // STUB
        RateLimiter::shouldReceive('hit')->once();                  // MOCK
        RateLimiter::shouldReceive('clear')->never();

        // Act & Assert
        try {
            $this->makeRequest()->authenticate();
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    /** TC-02: kredensial benar -> tidak ada exception, hitungan gagal direset (MOCK clear). */
    public function test_tc02_kredensial_benar_mereset_rate_limiter(): void
    {
        // Arrange
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
        Auth::shouldReceive('attempt')->once()->andReturn(true);    // STUB
        RateLimiter::shouldReceive('clear')->once();                // MOCK
        RateLimiter::shouldReceive('hit')->never();

        // Act
        $this->makeRequest()->authenticate();

        // Assert
        $this->assertTrue(true); // expectation Mockery diverifikasi saat tearDown
    }

    /** TC-03: sudah 5x gagal -> terkunci, event Lockout dikirim, Auth::attempt tidak dipanggil. */
    public function test_tc03_akun_terkunci_setelah_terlalu_banyak_percobaan(): void
    {
        // Arrange
        Event::fake([Lockout::class]);                               // FAKE
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(120);
        Auth::shouldReceive('attempt')->never();

        // Act & Assert
        try {
            $this->makeRequest()->ensureIsNotRateLimited();
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            Event::assertDispatched(Lockout::class);
        }
    }

    /** TC-04: belum terkunci -> ensureIsNotRateLimited() keluar tanpa efek. */
    public function test_tc04_belum_terkunci_tidak_melempar_exception(): void
    {
        // Arrange
        Event::fake([Lockout::class]);
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);

        // Act
        $this->makeRequest()->ensureIsNotRateLimited();

        // Assert
        Event::assertNotDispatched(Lockout::class);
    }

    /** TC-05: aturan validasi menolak email kosong/format salah dan password kosong. */
    public function test_tc05_rules_menolak_input_tidak_valid(): void
    {
        // Act
        $rules = (new LoginRequest())->rules();

        // Assert
        $invalid = Validator::make(['email' => 'bukan-email', 'password' => ''], $rules);
        $valid   = Validator::make(['email' => 'budi@example.com', 'password' => 'x'], $rules);

        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('email', $invalid->errors()->toArray());
        $this->assertArrayHasKey('password', $invalid->errors()->toArray());
        $this->assertFalse($valid->fails());
    }

    /** TC-06: throttleKey memakai email huruf kecil + IP. */
    public function test_tc06_throttle_key_memakai_email_lowercase_dan_ip(): void
    {
        // Act
        $key = $this->makeRequest('BUDI@Example.COM')->throttleKey();

        // Assert
        $this->assertStringStartsWith('budi@example.com|', $key);
    }

    /** TC-07: authorize() selalu true. */
    public function test_tc07_authorize_selalu_true(): void
    {
        // Act & Assert
        $this->assertTrue((new LoginRequest())->authorize());
    }

    /** TC-08 & TC-09: controller store() -> admin ke dashboard admin, customer ke dashboard biasa. */
    public function test_tc08_store_admin_diarahkan_ke_dashboard_admin(): void
    {
        // Act
        $response = $this->callStore('admin');

        // Assert
        $this->assertStringEndsWith('/admin/dashboard', $response->getTargetUrl());
    }

    public function test_tc09_store_customer_diarahkan_ke_dashboard_biasa(): void
    {
        // Act
        $response = $this->callStore('customer');

        // Assert
        $this->assertStringEndsWith('/dashboard', $response->getTargetUrl());
        $this->assertStringNotContainsString('/admin', $response->getTargetUrl());
    }

    private function callStore(string $role)
    {
        $user = (new User())->forceFill(['role' => $role]);

        $session = Mockery::mock();                                  // MOCK
        $session->shouldReceive('regenerate')->once();

        $request = Mockery::mock(LoginRequest::class)->makePartial();
        $request->shouldReceive('authenticate')->once();             // MOCK (dependensi diisolasi)
        $request->shouldReceive('session')->andReturn($session);
        $request->shouldReceive('user')->andReturn($user);

        return app(AuthenticatedSessionController::class)->store($request);
    }
}
