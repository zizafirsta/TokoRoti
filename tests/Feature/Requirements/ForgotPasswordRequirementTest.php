<?php

namespace Tests\Feature\Requirements;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** FS-003 Forgot Password: RQ-009 s/d RQ-012 */
class ForgotPasswordRequirementTest extends TestCase
{
    use RefreshDatabase;

    /** RQ-009 */
    public function test_rq009_reset_link_is_sent_to_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        $response->assertSessionHas('status', trans('passwords.sent'));
    }

    /** RQ-010 */
    public function test_rq010_unregistered_email_shows_proper_message(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'tidakada@example.com']);

        Notification::assertNothingSent();
        $response->assertSessionHasErrors(['email' => trans('passwords.user')]);
    }

    /** RQ-011 */
    public function test_rq011_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // masa berlaku token = config('auth.passwords.users.expire') menit (default 60)
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertSessionHasErrors(['email' => trans('passwords.token')]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /** RQ-011 (pembanding): token yang masih berlaku diterima. */
    public function test_rq011_valid_token_is_accepted(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }

    /** RQ-012: password baru valid diterima. */
    public function test_rq012_valid_new_password_is_accepted(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('passwordbaru123', $user->fresh()->password));
    }

    /** RQ-012: password baru tidak valid (pendek / konfirmasi beda / kosong) ditolak. */
    public function test_rq012_invalid_new_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $base = ['token' => $token, 'email' => $user->email];

        $this->post('/reset-password', $base + ['password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', $base + ['password' => 'passwordbaru123', 'password_confirmation' => 'beda'])
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', $base + ['password' => '', 'password_confirmation' => ''])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
