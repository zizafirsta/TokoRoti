<?php

namespace Tests\Unit\Auth;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * White-box test fitur REGISTER
 * Unit: RegisteredUserController::store
 *
 * Test Double:
 *  - FAKE : SQLite in-memory (phpunit.xml) menggantikan MySQL; Event::fake() untuk event Registered
 *  - MOCK : Auth::login() wajib dipanggil sekali dengan objek User
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'                  => 'Budi Santoso',
            'email'                 => 'budi@example.com',
            'phone'                 => '081234567890',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ], $override);
    }

    private function callStore(array $data)
    {
        return app(RegisteredUserController::class)->store(Request::create('/register', 'POST', $data));
    }

    /** TC-01: data valid -> user dibuat, password di-hash, role customer, redirect dashboard. */
    public function test_tc01_register_valid_membuat_user_customer(): void
    {
        // Arrange
        Event::fake([Registered::class]);                                   // FAKE
        Auth::shouldReceive('login')->once()->with(\Mockery::type(User::class)); // MOCK

        // Act
        $response = $this->callStore($this->payload());

        // Assert
        $user = User::where('email', 'budi@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertStringEndsWith('/dashboard', $response->getTargetUrl());
        Event::assertDispatched(Registered::class);
    }

    /** TC-02: input "role=admin" dari request diabaikan -> tetap customer. */
    public function test_tc02_role_admin_dari_request_diabaikan(): void
    {
        // Arrange
        Event::fake([Registered::class]);
        Auth::shouldReceive('login')->once();

        // Act
        $this->callStore($this->payload(['role' => 'admin']));

        // Assert
        $this->assertSame('customer', User::where('email', 'budi@example.com')->value('role'));
    }

    /** TC-03: email sudah terdaftar -> ValidationException, user tidak bertambah. */
    public function test_tc03_email_duplikat_ditolak(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        Auth::shouldReceive('login')->never();

        // Act & Assert
        $this->assertValidationFails($this->payload(), 'email');
        $this->assertSame(1, User::count());
    }

    /** TC-04..TC-08: tiap aturan validasi (name, email, phone, password) menolak input salah. */
    #[DataProvider('invalidProvider')]
    public function test_tc04_input_tidak_valid_ditolak(array $override, string $errorKey): void
    {
        Auth::shouldReceive('login')->never();

        // Act & Assert
        $this->assertValidationFails($this->payload($override), $errorKey);
        $this->assertSame(0, User::count());
    }

    public static function invalidProvider(): array
    {
        return [
            'nama kosong'             => [['name' => ''], 'name'],
            'email format salah'      => [['email' => 'bukan-email'], 'email'],
            'email huruf besar'       => [['email' => 'BUDI@EXAMPLE.COM'], 'email'],
            'no hp format salah'      => [['phone' => '12345'], 'phone'],
            'no hp kosong'            => [['phone' => ''], 'phone'],
            'password terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password'],
            'konfirmasi tidak sama'   => [['password_confirmation' => 'Beda12345'], 'password'],
        ];
    }

    private function assertValidationFails(array $data, string $key): void
    {
        try {
            $this->callStore($data);
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
