<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_branches(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();

        $id = $this->actingAs($super)->postJson('/api/branches', ['name' => 'Kharadi', 'phone' => '+912000000000'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/branches', ['name' => 'Kharadi'])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->putJson("/api/branches/{$id}", ['name' => 'Kharadi East', 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->getJson('/api/branches')->assertOk()->assertJsonPath('data.0.name', 'Kharadi East');
    }

    public function test_deleting_branch_unlinks_its_users(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $branch = Branch::create(['name' => 'Wakad']);
        $staff = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($super)->deleteJson("/api/branches/{$branch->id}")->assertNoContent();

        $this->assertNull($staff->fresh()->branch_id);
    }

    public function test_non_super_admins_can_only_list_branches(): void
    {
        $branch = Branch::create(['name' => 'Aundh']);

        foreach ([Role::Admin, Role::Assignee] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->getJson('/api/branches')->assertOk();
            $this->postJson('/api/branches', ['name' => 'New'])->assertForbidden();
            $this->putJson("/api/branches/{$branch->id}", ['name' => 'X'])->assertForbidden();
            $this->deleteJson("/api/branches/{$branch->id}")->assertForbidden();
        }
    }
}
