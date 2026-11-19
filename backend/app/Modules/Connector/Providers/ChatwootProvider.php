<?php

namespace App\Modules\Connector\Providers;

use App\Modules\Connector\ChatwootPayload;
use App\Modules\Connector\Data\SentMessage;
use App\Modules\Connector\WhatsAppProvider;
use App\Modules\Leads\Models\Contact;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ChatwootProvider implements WhatsAppProvider
{
    public function verifyWebhook(Request $request): bool
    {
        $token = (string) $this->config('webhook_token');

        return $token !== '' && hash_equals($token, (string) $request->query('token'));
    }

    public function webhookEventId(array $payload): ?string
    {
        return ChatwootPayload::eventId($payload, $this->config('inbox_id'));
    }

    public function parseWebhook(array $payload): array
    {
        return ChatwootPayload::parse($payload);
    }

    public function sendText(Contact $contact, string $text): SentMessage
    {
        $response = $this->http()
            ->post($this->conversationPath($contact).'/messages', ['content' => $text, 'message_type' => 'outgoing', 'private' => false])
            ->throw();

        return new SentMessage((string) $response->json('id'));
    }

    public function sendMedia(Contact $contact, string $path, string $fileName, ?string $caption): SentMessage
    {
        $response = $this->http()
            ->attach('attachments[]', Storage::disk('local')->get($path), $fileName)
            ->post($this->conversationPath($contact).'/messages', array_filter(['content' => $caption, 'message_type' => 'outgoing']))
            ->throw();

        return new SentMessage((string) $response->json('id'));
    }

    public function downloadMedia(string $url): string
    {
        return Http::timeout(30)->get($url)->throw()->body();
    }

    public function checkConnection(): string
    {
        $inbox = $this->http()->get('inboxes/'.$this->config('inbox_id'))->throw();

        if (blank($this->config('webhook_token'))) {
            throw new RuntimeException('API works, but CHATWOOT_WEBHOOK_TOKEN is empty, so incoming webhooks will be rejected.');
        }

        return "Connected to Chatwoot inbox \"{$inbox->json('name')}\" ({$inbox->json('channel_type')}).";
    }

    private function conversationPath(Contact $contact): string
    {
        if (! $contact->provider_conversation_id) {
            $contact->provider_conversation_id = $this->createConversation($contact);
            if ($contact->exists) {
                $contact->save();
            }
        }

        return 'conversations/'.$contact->provider_conversation_id;
    }

    private function createConversation(Contact $contact): string
    {
        $inboxId = $this->config('inbox_id');
        $created = $this->http()->post('contacts', [
            'inbox_id' => $inboxId,
            'name' => $contact->name ?: $contact->phone,
            'phone_number' => $contact->phone,
        ]);

        if ($created->successful()) {
            $contactId = $created->json('payload.contact.id');
            $sourceId = $created->json('payload.contact_inbox.source_id');
        } else {
            $contactId = $this->http()->get('contacts/search', ['q' => $contact->phone])->throw()->json('payload.0.id')
                ?? throw new RuntimeException("Chatwoot contact {$contact->phone} could not be created or found.");
            $sourceId = $this->http()->post("contacts/{$contactId}/contact_inboxes", ['inbox_id' => $inboxId])->throw()->json('source_id');
        }

        return (string) $this->http()
            ->post('conversations', ['source_id' => $sourceId, 'inbox_id' => $inboxId, 'contact_id' => $contactId])
            ->throw()
            ->json('id');
    }

    private function http(): PendingRequest
    {
        foreach (['base_url', 'account_id', 'inbox_id', 'api_token'] as $key) {
            if (blank($this->config($key))) {
                throw new RuntimeException('Chatwoot is not configured: set CHATWOOT_'.strtoupper($key).' in .env.');
            }
        }

        return Http::baseUrl(rtrim($this->config('base_url'), '/').'/api/v1/accounts/'.$this->config('account_id'))
            ->withHeaders(['api_access_token' => $this->config('api_token')])
            ->acceptJson()
            ->timeout(20);
    }

    private function config(string $key): mixed
    {
        return config("whatsapp.chatwoot.{$key}");
    }
}
