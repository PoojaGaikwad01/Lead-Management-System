<?php

namespace App\Modules\Connector\Providers;

use App\Modules\Connector\ChatwootPayload;
use App\Modules\Connector\Data\SentMessage;
use App\Modules\Connector\MockStatusJob;
use App\Modules\Connector\WhatsAppProvider;
use App\Modules\Leads\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Local stand-in for Chatwoot: accepts Chatwoot-shaped payloads and fakes delivery. */
class MockProvider implements WhatsAppProvider
{
    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function webhookEventId(array $payload): ?string
    {
        return ChatwootPayload::eventId($payload, null);
    }

    public function parseWebhook(array $payload): array
    {
        return ChatwootPayload::parse($payload);
    }

    public function sendText(Contact $contact, string $text): SentMessage
    {
        return $this->sent();
    }

    public function sendMedia(Contact $contact, string $path, string $fileName, ?string $caption): SentMessage
    {
        return $this->sent();
    }

    public function downloadMedia(string $url): string
    {
        if (str_starts_with($url, 'mock://')) {
            return Storage::disk('local')->get(rawurldecode(substr($url, 7))) ?? throw new RuntimeException("Mock file not found: {$url}");
        }

        return Http::timeout(30)->get($url)->throw()->body();
    }

    public function checkConnection(): string
    {
        return 'The mock connector is active. No real WhatsApp messages are sent or received.';
    }

    private function sent(): SentMessage
    {
        $id = 'mock-'.Str::uuid();
        MockStatusJob::dispatch($id, 'delivered')->delay(now()->addSeconds(2));
        MockStatusJob::dispatch($id, 'read')->delay(now()->addSeconds(5));

        return new SentMessage($id);
    }
}
