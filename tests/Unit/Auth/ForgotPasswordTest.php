<?php

namespace Tests\Unit\Auth;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * White-box test fitur FORGOT PASSWORD
 * Unit: PasswordResetLinkController::store, NewPasswordController::store
 *
 * Test Double:
 *  - STUB : Password::sendResetLink() / Password::reset() mengembalikan status tetap (tanpa kirim email/token asli)
 *  - MOCK : verifikasi dipanggil sekali / tidak dipanggil sama sekali (->never())
 *  - FAKE : Event::fake() + SQLite in-memory
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function req(string $uri, array $data): Request
    {
        return Request::create($uri, 'POST', $data);
    }

    /** TC-01: email terdaftar -> link terkirim, pesan status ada. */
    public function test_tc01_link_reset_berhasil_dikirim(): void
    {
        // Arrange
        Password::shouldReceive('sendResetLink')->once()
            ->with(['email' => 'budi@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);                  // STUB + MOCK

        // Act
        $response = app(PasswordResetLinkController::class)
            ->store($this->req('/forgot-password', ['email' => 'budi@example.com']));

        // Assert
        $this->assertNotNull($response->getSession()->get('status'));
    }

    /** TC-02: email tidak terdaftar -> kembali dengan error pada field email. */
    public function test_tc02_email_tidak_terdaftar_menampilkan_error(): void
    {
        // Arrange
        Password::shouldReceive('sendResetLink')->once()->andReturn(Password::INVALID_USER);

        // Act
        $response = app(PasswordResetLinkController::class)
            ->store($this->req('/forgot-password', ['email' => 'hantu@example.com']));

        // Assert
        $this->assertTrue($response->getSession()->get('errors')->has('email'));
    }

    /** TC-03: email kosong / format salah -> ValidationException, broker tidak dipanggil. */
    public function test_tc03_email_tidak_valid_ditolak_sebelum_broker_dipanggil(): void
    {
        // Arrange
        Password::shouldReceive('sendResetLink')->never();

        $this->expectException(ValidationException::class);

        // Act
        app(PasswordResetLinkController::class)->store($this->req('/forgot-password', ['email' => 'bukan-email']));
    }

    /** TC-04: reset password sukses -> redirect ke login dengan status. */
    public function test_tc04_reset_password_sukses_redirect_ke_login(): void
    {
        // Arrange
        Password::shouldReceive('reset')->once()->andReturn(Password::PASSWORD_RESET); // STUB

        // Act
        $response = app(NewPasswordController::class)->store($this->req('/reset-password', $this->resetData()));

        // Assert
        $this->assertStringEndsWith('/login', $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('status'));
    }

    /** TC-05: token tidak valid -> kembali dengan error email. */
    public function test_tc05_token_tidak_valid_menampilkan_error(): void
    {
        // Arrange
        Password::shouldReceive('reset')->once()->andReturn(Password::INVALID_TOKEN);

        // Act
        $response = app(NewPasswordController::class)->store($this->req('/reset-password', $this->resetData()));

        // Assert
        $this->assertTrue($response->getSession()->get('errors')->has('email'));
    }

    /** TC-06: callback reset benar-benar mengganti password + mengirim event PasswordReset. */
    public function test_tc06_callback_mengganti_password_dan_mengirim_event(): void
    {
        // Arrange
        Event::fake([PasswordReset::class]);                         // FAKE
        $user = User::factory()->create(['email' => 'budi@example.com']);

        Password::shouldReceive('reset')->once()->andReturnUsing(
            function (array $credentials, callable $callback) use ($user) {
                $callback($user);   // STUB menjalankan callback seolah token valid

                return Password::PASSWORD_RESET;
            }
        );

        // Act
        app(NewPasswordController::class)->store($this->req('/reset-password', $this->resetData()));

        // Assert
        $this->assertTrue(Hash::check('PasswordBaru123', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    /** TC-07: konfirmasi password tidak sama / token kosong -> ValidationException. */
    public function test_tc07_konfirmasi_password_tidak_sama_ditolak(): void
    {
        // Arrange
        Password::shouldReceive('reset')->never();

        $this->expectException(ValidationException::class);

        // Act
        app(NewPasswordController::class)->store(
            $this->req('/reset-password', $this->resetData(['password_confirmation' => 'Beda12345']))
        );
    }

    private function resetData(array $override = []): array
    {
        return array_merge([
            'token'                 => 'token-dummy',
            'email'                 => 'budi@example.com',
            'password'              => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ], $override);
    }
}
