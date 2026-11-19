<?php

namespace Tests\Feature;

use App\Modules\Inbox\Message;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $id, string $phone, array $overrides = []): array
    {
        $sender = ['id' => 7, 'name' => 'Priya', 'phone_number' => $phone, 'type' => 'contact'];

        return [
            'event' => 'message_created',
            'id' => $id,
            'content' => 'Hi, birthday party for 15 kids?',
            'message_type' => 'incoming',
            'private' => false,
            'created_at' => '2026-11-20T10:00:00Z',
            'sender' => $sender,
            'conversation' => ['id' => 55, 'inbox_id' => 1, 'meta' => ['sender' => $sender]],
            'inbox' => ['id' => 1],
            'attachments' => [],
            ...$overrides,
        ];
    }

    public function test_incoming_message_creates_contact_lead_and_message_once(): void
    {
        $payload = $this->payload(101, '+919876543210');

        $this->postJson('/api/webhooks/whatsapp/mock', $payload)->assertOk()->assertJsonPath('status', 'queued');
        $this->postJson('/api/webhooks/whatsapp/mock', $payload)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, Contact::count());
        $this->assertSame(1, Message::count());
        $lead = Lead::sole();
        $this->assertSame(LeadSource::WhatsappEnquiry, $lead->source);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame(1, $lead->unread_count);
        $this->assertSame('55', Contact::sole()->provider_conversation_id);
    }

    public function test_same_provider_message_id_is_stored_once_even_with_a_different_event(): void
    {
        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(202, '+919876543210'));
        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(202, '+919876543210', ['event' => 'message_created', 'content' => 'retry']))
            ->assertJsonPath('status', 'duplicate');

        app(\App\Modules\Inbox\InboundMessageHandler::class)->receive(
            \App\Modules\Connector\ChatwootPayload::parse($this->payload(202, '+919876543210'))[0],
            app(\App\Modules\Connector\WhatsAppManager::class)->driver(),
        );

        $this->assertSame(1, Message::count());
    }

    public function test_phone_formats_dedupe_to_one_contact_and_open_lead(): void
    {
        foreach (['+91 98765 43210', '919876543210', '09876543210'] as $i => $phone) {
            $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(300 + $i, $phone))->assertOk();
        }

        $this->assertSame('+919876543210', Contact::sole()->phone);
        $this->assertSame(1, Lead::count());
        $this->assertSame(3, Lead::sole()->unread_count);
    }

    public function test_message_after_lead_is_closed_starts_a_new_lead(): void
    {
        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(401, '+919876543210'));
        Lead::sole()->update(['status' => LeadStatus::Converted]);

        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(402, '+919876543210'));

        $this->assertSame(2, Lead::count());
        $this->assertSame(1, Contact::count());
    }

    public function test_agency_number_messages_are_flagged_and_create_no_lead(): void
    {
        Setting::put('agency_numbers', ['+919000000001']);

        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(501, '+91 90000 00001'));

        $this->assertTrue(Message::sole()->is_agency);
        $this->assertNull(Message::sole()->lead_id);
        $this->assertSame(0, Lead::count());
    }

    public function test_attachment_is_downloaded_and_stored(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('simulator/abc/leads list.csv', "name,phone\nAsha,9876500000\n");

        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(601, '+919876543210', [
            'content' => null,
            'attachments' => [['file_type' => 'file', 'data_url' => 'mock://simulator/abc/leads%20list.csv']],
        ]));

        $message = Message::sole();
        $this->assertSame('document', $message->type);
        $this->assertSame('leads list.csv', $message->media_name);
        $this->assertSame('text/csv', $message->media_mime);
        Storage::disk('local')->assertExists($message->media_url);
    }

    public function test_only_the_active_provider_accepts_webhooks_and_chatwoot_requires_token(): void
    {
        $this->postJson('/api/webhooks/whatsapp/chatwoot', $this->payload(701, '+919876543210'))->assertNotFound();

        config(['whatsapp.provider' => 'chatwoot', 'whatsapp.chatwoot.webhook_token' => 'secret-token']);

        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(702, '+919876543210'))->assertNotFound();
        $this->postJson('/api/webhooks/whatsapp/chatwoot', $this->payload(703, '+919876543210'))->assertUnauthorized();
        $this->postJson('/api/webhooks/whatsapp/chatwoot?token=wrong', $this->payload(704, '+919876543210'))->assertUnauthorized();
        $this->postJson('/api/webhooks/whatsapp/chatwoot?token=secret-token', $this->payload(705, '+919876543210'))
            ->assertOk()->assertJsonPath('status', 'queued');

        $this->assertSame(1, Message::count());
    }

    public function test_status_updates_only_move_forward(): void
    {
        $this->postJson('/api/webhooks/whatsapp/mock', $this->payload(801, '+919876543210', ['message_type' => 'outgoing', 'sender' => ['type' => 'user', 'name' => 'Agent']]));
        $update = fn (string $status) => $this->postJson('/api/webhooks/whatsapp/mock', [
            'event' => 'message_updated', 'id' => 801, 'message_type' => 'outgoing', 'private' => false, 'status' => $status,
        ]);

        $update('read');
        $update('delivered');

        $message = Message::sole();
        $this->assertSame('out', $message->direction);
        $this->assertSame('read', $message->status);
        $this->assertSame(0, Lead::sole()->unread_count);
    }
}
