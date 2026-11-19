<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\Reminder;
use App\Modules\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->staff = User::factory()->create(['name' => 'Asha']);
    }

    private function lead(array $attributes = []): Lead
    {
        $contact = Contact::create(['phone' => '+9198'.random_int(10000000, 99999999), 'name' => 'Priya']);

        return Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry, ...$attributes]);
    }

    private function assigned(): Lead
    {
        $lead = $this->lead();
        $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->staff->id])->assertOk();

        return $lead->refresh();
    }

    public function test_admin_assigns_and_assignee_is_notified(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->staff)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->staff->id])->assertForbidden();

        $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->staff->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.reply_status', 'not_sent')
            ->assertJsonPath('data.assignee.name', 'Asha');

        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame('assigned', $this->staff->notifications()->first()->data['kind']);
        $this->getJson("/api/leads/{$lead->id}/activity")->assertJsonPath('data.activity.0.text', "{$this->admin->name} assigned to Asha");
    }

    public function test_branch_assignment_notifies_branch_staff(): void
    {
        $branch = Branch::create(['name' => 'Baner']);
        $this->staff->update(['branch_id' => $branch->id]);
        $lead = $this->lead();

        $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/assign", ['branch_id' => $branch->id])->assertOk();

        $this->assertSame(1, $this->staff->notifications()->count());
        $this->actingAs($this->staff)->getJson("/api/leads/{$lead->id}")->assertOk();
    }

    public function test_assignee_marks_reply_sent_with_note_and_outsiders_cannot(): void
    {
        $lead = $this->assigned();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson("/api/leads/{$lead->id}/reply-sent")->assertForbidden();

        $this->actingAs($this->staff)->postJson("/api/leads/{$lead->id}/reply-sent", ['note' => 'Shared party packages'])
            ->assertOk()
            ->assertJsonPath('data.reply_status', 'sent')
            ->assertJsonPath('data.status', 'contacted');
        $this->postJson("/api/leads/{$lead->id}/reply-sent")->assertUnprocessable();
        $this->getJson("/api/leads/{$lead->id}/activity")->assertJsonPath('data.notes.0.body', 'Shared party packages');
    }

    public function test_status_changes_require_lost_reason_and_track_conversion(): void
    {
        $lead = $this->assigned();
        $this->actingAs($this->staff);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors('lost_reason');
        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'lost', 'lost_reason' => 'Price too high'])
            ->assertOk()->assertJsonPath('data.lost_reason', 'Price too high');
        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])->assertOk()->assertJsonPath('data.lost_reason', null);

        $lead->refresh();
        $this->assertSame(LeadStatus::Converted, $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertNull($lead->closed_at);
    }

    public function test_admin_reminds_assignee_and_reminders_are_tracked(): void
    {
        $lead = $this->assigned();

        $this->actingAs($this->staff)->postJson("/api/leads/{$lead->id}/remind")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/remind")
            ->assertOk()
            ->assertJsonPath('data.reminder_count', 1)
            ->assertJsonPath('data.last_reminder_at', fn ($v) => $v !== null);

        $this->assertSame('in_app', Reminder::sole()->channel);
        $this->assertEqualsCanonicalizing(['assigned', 'reminder'], $this->staff->notifications()->get()->pluck('data.kind')->all());

        $lead->update(['reply_status' => ReplyStatus::Sent]);
        $this->postJson("/api/leads/{$lead->id}/remind")->assertUnprocessable();
    }

    public function test_auto_reminders_are_off_by_default_and_respect_the_window(): void
    {
        $lead = $this->assigned();
        $lead->update(['assigned_at' => now()->subHours(3)]);

        $this->artisan('leads:auto-remind')->expectsOutputToContain('0 automatic')->assertSuccessful();

        Setting::put('reminder_rules', ['auto_enabled' => true, 'after_hours' => 2]);
        $this->artisan('leads:auto-remind')->expectsOutputToContain('1 automatic');
        $this->artisan('leads:auto-remind')->expectsOutputToContain('0 automatic');

        $this->assertTrue(Reminder::sole()->auto);
        $this->assertSame(1, $lead->fresh()->reminder_count);
    }

    public function test_notes_calls_and_notifications_api(): void
    {
        $lead = $this->assigned();
        $this->actingAs($this->staff);

        $this->postJson("/api/leads/{$lead->id}/notes", ['kind' => 'call'])->assertUnprocessable()->assertJsonValidationErrors('call_outcome');
        $this->postJson("/api/leads/{$lead->id}/notes", ['kind' => 'call', 'call_outcome' => 'no_answer'])
            ->assertCreated()
            ->assertJsonPath('data.notes.0.call_outcome', 'no_answer')
            ->assertJsonPath('data.activity.0.text', 'Asha logged a call (no answer)');

        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.lead_id', $lead->id);
        $this->postJson('/api/notifications/read')->assertNoContent();
        $this->getJson('/api/notifications')->assertJsonPath('unread', 0);
    }

    public function test_messages_from_staff_numbers_do_not_create_leads(): void
    {
        $this->staff->update(['phone' => '+919811100000']);
        $sender = ['name' => 'Asha', 'phone_number' => '+91 98111 00000', 'type' => 'contact'];

        $this->postJson('/api/webhooks/whatsapp/mock', [
            'event' => 'message_created', 'id' => 99, 'content' => 'ok noted', 'message_type' => 'incoming', 'private' => false,
            'sender' => $sender, 'conversation' => ['id' => 9, 'meta' => ['sender' => $sender]], 'attachments' => [],
        ])->assertOk();

        $this->assertSame(0, Lead::count());
    }
}
