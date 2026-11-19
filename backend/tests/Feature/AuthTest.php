<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function spa(): static
    {
        return $this->withHeader('Referer', 'http://localhost:5173');
    }

    public function test_user_can_log_in_and_fetch_profile(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->spa()->postJson('/api/auth/login', ['email' => 'staff@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'staff@example.com')
            ->assertJsonPath('data.role', 'assignee')
            ->assertJsonMissingPath('data.password');

        $this->spa()->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'staff@example.com');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->spa()->postJson('/api/auth/login', ['email' => 'staff@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'old@example.com', 'active' => false]);

        $this->spa()->postJson('/api/auth/login', ['email' => 'old@example.com', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_deactivated_user_session_is_rejected(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_cannot_access_api(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/branches')->assertUnauthorized();
    }

    public function test_user_can_log_out(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);
        $this->spa()->postJson('/api/auth/login', ['email' => 'staff@example.com', 'password' => 'password']);

        $this->spa()->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }
}
