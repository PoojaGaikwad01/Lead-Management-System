<?php

namespace App\Modules\Connector;

use App\Modules\Connector\Data\IncomingMessage;
use App\Modules\Connector\Data\SentMessage;
use App\Modules\Connector\Data\StatusUpdate;
use App\Modules\Leads\Models\Contact;
use Illuminate\Http\Request;

interface WhatsAppProvider
{
    public function verifyWebhook(Request $request): bool;

    /** Unique id for de-duplicating webhook deliveries; null means the payload is ignored. */
    public function webhookEventId(array $payload): ?string;

    /** @return array<IncomingMessage|StatusUpdate> */
    public function parseWebhook(array $payload): array;

    public function sendText(Contact $contact, string $text): SentMessage;

    public function sendMedia(Contact $contact, string $path, string $fileName, ?string $caption): SentMessage;

    public function downloadMedia(string $url): string;

    /** Human-readable confirmation that credentials work; throws on failure. */
    public function checkConnection(): string;
}
