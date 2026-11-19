<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LeadVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $attributes = []): Lead
    {
        $contact = Contact::create(['phone' => '+9198'.random_int(10000000, 99999999)]);

        return Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry, ...$attributes]);
    }

    public function test_assignee_sees_only_own_and_branch_leads(): void
    {
        $branch = Branch::create(['name' => 'Baner']);
        $otherBranch = Branch::create(['name' => 'Wakad']);
        $staff = User::factory()->create(['branch_id' => $branch->id]);

        $own = $this->lead(['assigned_to' => $staff->id]);
        $branchLead = $this->lead(['branch_id' => $branch->id]);
        $foreign = $this->lead(['branch_id' => $otherBranch->id]);
        $unassigned = $this->lead();

        $visible = Lead::visibleTo($staff)->pluck('id')->sort()->values()->all();
        $this->assertSame([$own->id, $branchLead->id], $visible);

        $gate = Gate::forUser($staff);
        $this->assertTrue($gate->allows('view', $own));
        $this->assertTrue($gate->allows('update', $branchLead));
        $this->assertFalse($gate->allows('view', $foreign));
        $this->assertFalse($gate->allows('view', $unassigned));
        $this->assertFalse($gate->allows('assign', $own));
        $this->assertFalse($gate->allows('remind', $own));
    }

    public function test_assignee_without_branch_sees_only_assigned_leads(): void
    {
        $staff = User::factory()->create();
        $own = $this->lead(['assigned_to' => $staff->id]);
        $this->lead(['branch_id' => Branch::create(['name' => 'Baner'])->id]);
        $this->lead();

        $this->assertSame([$own->id], Lead::visibleTo($staff)->pluck('id')->all());
    }

    public function test_admins_see_and_manage_all_leads(): void
    {
        $lead = $this->lead();
        $this->lead(['branch_id' => Branch::create(['name' => 'Baner'])->id]);

        foreach ([Role::Admin, Role::SuperAdmin] as $role) {
            $admin = User::factory()->role($role)->create();
            $this->assertSame(2, Lead::visibleTo($admin)->count());
            $this->assertTrue(Gate::forUser($admin)->allows('assign', $lead));
            $this->assertTrue(Gate::forUser($admin)->allows('remind', $lead));
            $this->assertTrue(Gate::forUser($admin)->allows('view-reports'));
        }
    }

    public function test_only_super_admin_can_manage_settings(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->role(Role::SuperAdmin)->create())->allows('manage-settings'));
        $this->assertFalse(Gate::forUser(User::factory()->role(Role::Admin)->create())->allows('manage-settings'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view-reports'));
    }
}
