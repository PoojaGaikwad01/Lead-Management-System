<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadInboxTest extends TestCase
{
    use RefreshDatabase;

    private function lead(string $name, array $attributes = []): Lead
    {
        $contact = Contact::create(['phone' => '+9198'.random_int(10000000, 99999999), 'name' => $name]);

        return Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry, ...$attributes]);
    }

    public function test_assignee_list_summary_and_detail_are_limited_to_visible_leads(): void
    {
        $branch = Branch::create(['name' => 'Baner']);
        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $own = $this->lead('Own', ['assigned_to' => $staff->id, 'assigned_at' => now(), 'reply_status' => ReplyStatus::NotSent]);
        $this->lead('Branch', ['branch_id' => $branch->id]);
        $hidden = $this->lead('Hidden');

        $this->actingAs($staff);
        $this->getJson('/api/leads')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/leads?tab=mine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.contact.name', 'Own');
        $this->getJson('/api/leads?q=hidden')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/leads/summary')->assertOk()
            ->assertJsonPath('data.tabs.all', 2)
            ->assertJsonPath('data.tabs.reply_not_sent', 1);
        $this->getJson("/api/leads/{$own->id}")->assertOk()->assertJsonPath('data.reply_due_at', fn ($v) => $v !== null);
        $this->getJson("/api/leads/{$hidden->id}")->assertForbidden();
        $this->postJson("/api/leads/{$hidden->id}/read")->assertForbidden();
    }

    public function test_admin_sees_all_and_can_filter_and_mark_read(): void
    {
        $lead = $this->lead('Priya', ['unread_count' => 3]);
        $this->lead('Rahul', ['source' => LeadSource::AgencyText]);

        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $this->getJson('/api/leads')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/leads?source=agency_text')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/leads?q='.substr($lead->contact->phone, -6))->assertOk()->assertJsonPath('data.0.id', $lead->id);
        $this->getJson('/api/leads/summary')->assertJsonPath('data.unread', 1)->assertJsonPath('data.cards.unassigned', 2);

        $this->postJson("/api/leads/{$lead->id}/read")->assertNoContent();
        $this->assertSame(0, $lead->fresh()->unread_count);
    }
}
