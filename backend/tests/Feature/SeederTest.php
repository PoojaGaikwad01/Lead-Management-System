<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Leads\Models\LeadType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_default_lead_types_and_super_admin_idempotently(): void
    {
        config(['lms.super_admin' => ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-123']]);

        $this->seed();
        $this->seed();

        $this->assertSame(
            ['Birthday Party', 'Play Zone Visit', 'Membership', 'Events/School Trip', 'Corporate/Group', 'Franchise', 'General'],
            LeadType::orderBy('sort')->pluck('name')->all(),
        );
        $this->assertSame(['party_date', 'kids_count'], LeadType::where('slug', 'birthday-party')->first()->fields->pluck('key')->all());
        $this->assertSame(['city', 'budget'], LeadType::where('slug', 'franchise')->first()->fields->pluck('key')->all());

        $this->assertSame(1, User::count());
        $this->assertSame(Role::SuperAdmin, User::first()->role);
    }

    public function test_super_admin_is_skipped_without_credentials(): void
    {
        config(['lms.super_admin' => ['name' => 'Owner', 'email' => null, 'password' => null]]);

        $this->seed();

        $this->assertSame(0, User::count());
    }
}
