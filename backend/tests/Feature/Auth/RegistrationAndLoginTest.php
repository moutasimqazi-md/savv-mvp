<?php

namespace Savv\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Savv\Models\User;
use Savv\Tests\TestCase;

class RegistrationAndLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_is_redirected_to_the_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Synthetic User',
            'email' => 'synthetic@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'synthetic@example.com']);
    }

    public function test_password_is_hashed_and_never_stored_in_plain_text(): void
    {
        $this->post('/register', [
            'name' => 'Synthetic User',
            'email' => 'synthetic@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ]);

        $user = User::where('email', 'synthetic@example.com')->firstOrFail();
        $this->assertNotSame('a-long-enough-password', $user->password);
        $this->assertTrue(str_starts_with($user->password, '$argon2id$'));
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        // Use Hash::make() (respects config/hashing.php's Argon2id driver),
        // never the bcrypt() helper - it's hardcoded to bcrypt and the
        // User model's 'hashed' cast rejects a hash that doesn't match the
        // currently configured driver.
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
