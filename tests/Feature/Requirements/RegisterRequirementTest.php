<?php

namespace Tests\Feature\Requirements;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FS-002 Register: RQ-005 s/d RQ-008 */
class RegisterRequirementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'                  => 'Budi Santoso',
            'email'                 => 'budi@example.com',
            'phone'                 => '081234567890',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ], $override);
    }

    /** RQ-005 */
    public function test_rq005_customer_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'name'  => 'Budi Santoso',
            'phone' => '081234567890',
        ]);
    }

    /** RQ-006 */
    public function test_rq006_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $response = $this->post('/register', $this->payload());

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
    }

    /** RQ-007: semua field wajib kosong. */
    public function test_rq007_empty_required_fields_are_rejected(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'phone', 'password']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** RQ-007: input tidak valid (format email, no. HP, password pendek, konfirmasi beda). */
    public function test_rq007_invalid_inputs_are_rejected(): void
    {
        $this->post('/register', $this->payload(['email' => 'bukan-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['phone' => 'abc123']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->payload(['password' => 'abc', 'password_confirmation' => 'abc']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'beda-semua']))->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /** RQ-008: akun baru berperan customer, walau request menyelundupkan role=admin. */
    public function test_rq008_new_account_never_gets_admin_role(): void
    {
        $this->post('/register', $this->payload(['role' => 'admin', 'is_active' => 0]));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertTrue($user->is_active);

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }
}
