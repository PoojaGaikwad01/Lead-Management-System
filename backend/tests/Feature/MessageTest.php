<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Inbox\Message;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $attributes = []): Lead
    {
        $contact = Contact::create(['phone' => '+9198765'.random_int(10000, 99999), 'name' => 'Priya']);

        return Lead::create(['contact_id' => $contact->id, 'source' => LeadSource::WhatsappEnquiry, ...$attributes]);
    }

    public function test_admin_sends_text_through_mock_connector(): void
    {
        $lead = $this->lead();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->postJson("/api/leads/{$lead->id}/messages", ['text' => 'Hello from Funky Monkey!'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'out');

        $message = Message::sole();
        $this->assertStringStartsWith('mock-', $message->provider_message_id);
        $this->assertNotNull($lead->fresh()->last_message_at);
        $this->getJson("/api/leads/{$lead->id}/messages")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_assignee_cannot_read_or_send_on_leads_they_cannot_see(): void
    {
        $lead = $this->lead();
        $own = $this->lead();
        $staff = User::factory()->create();
        $own->update(['assigned_to' => $staff->id]);

        $this->actingAs($staff);
        $this->getJson("/api/leads/{$lead->id}/messages")->assertForbidden();
        $this->postJson("/api/leads/{$lead->id}/messages", ['text' => 'Hi'])->assertForbidden();
        $this->postJson("/api/leads/{$own->id}/messages", ['text' => 'Hi'])->assertCreated();
    }

    public function test_failed_send_is_kept_and_reported(): void
    {
        config(['whatsapp.provider' => 'chatwoot', 'whatsapp.chatwoot' => [
            'base_url' => 'https://chat.example.com', 'account_id' => 1, 'inbox_id' => 2, 'api_token' => 'x', 'webhook_token' => 'y',
        ]]);
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $lead = $this->lead();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->postJson("/api/leads/{$lead->id}/messages", ['text' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonPath('data.status', 'failed');
    }
}
