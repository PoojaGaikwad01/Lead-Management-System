<?php

namespace App\Modules\Connector\Providers;

use App\Modules\Connector\Data\SentMessage;
use App\Modules\Connector\WhatsAppProvider;
use App\Modules\Leads\Models\Contact;
use Illuminate\Http\Request;
use RuntimeException;

/** Placeholder so WHATSAPP_PROVIDER=periskope can be implemented later without touching callers. */
class PeriskopeProvider implements WhatsAppProvider
{
    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function webhookEventId(array $payload): ?string
    {
        return null;
    }

    public function parseWebhook(array $payload): array
    {
        return [];
    }

    public function sendText(Contact $contact, string $text): SentMessage
    {
        throw new RuntimeException('The Periskope connector is not implemented yet.');
    }

    public function sendMedia(Contact $contact, string $path, string $fileName, ?string $caption): SentMessage
    {
        throw new RuntimeException('The Periskope connector is not implemented yet.');
    }

    public function downloadMedia(string $url): string
    {
        throw new RuntimeException('The Periskope connector is not implemented yet.');
    }

    public function checkConnection(): string
    {
        throw new RuntimeException('The Periskope connector is not implemented yet.');
    }
}
