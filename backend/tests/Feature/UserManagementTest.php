<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Branch Manager',
            'email' => 'manager@example.com',
            'phone' => '+919800000001',
            'role' => 'assignee',
            'branch_id' => null,
            'active' => true,
            'password' => 'secret-123',
            ...$overrides,
        ];
    }

    public function test_super_admin_can_create_update_and_delete_users(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $branch = Branch::create(['name' => 'Baner']);

        $id = $this->actingAs($super)->postJson('/api/users', $this->payload(['branch_id' => $branch->id]))
            ->assertCreated()
            ->assertJsonPath('data.branch.name', 'Baner')
            ->json('data.id');

        $this->putJson("/api/users/{$id}", $this->payload(['name' => 'Renamed', 'role' => 'admin', 'password' => null]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.role', 'admin');
        $this->assertTrue(Hash::check('secret-123', User::find($id)->password));

        $this->deleteJson("/api/users/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_validation_errors_are_returned(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create(['email' => 'taken@example.com']);

        $this->actingAs($super)->postJson('/api/users', $this->payload(['email' => 'taken@example.com', 'role' => 'owner', 'password' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role', 'password']);
    }

    public function test_admin_can_list_but_not_manage_users(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/users')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/users', $this->payload())->assertForbidden();
        $this->putJson("/api/users/{$other->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/users/{$other->id}")->assertForbidden();
    }

    public function test_assignee_cannot_list_users(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/users')->assertForbidden();
    }

    public function test_super_admin_cannot_lock_themselves_out(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create(['email' => 'boss@example.com']);

        $this->actingAs($super)
            ->putJson("/api/users/{$super->id}", $this->payload(['email' => 'boss@example.com', 'role' => 'admin', 'password' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->putJson("/api/users/{$super->id}", $this->payload(['email' => 'boss@example.com', 'role' => 'super_admin', 'active' => false, 'password' => null]))
            ->assertUnprocessable();

        $this->deleteJson("/api/users/{$super->id}")->assertForbidden();
    }
}
