<?php

namespace App\Modules\Connector;

use App\Modules\Connector\Data\Attachment;
use App\Modules\Connector\Data\IncomingMessage;
use App\Modules\Connector\Data\StatusUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/** Parses Chatwoot "message_created" / "message_updated" webhook payloads. */
class ChatwootPayload
{
    private const TYPES = ['image' => 'image', 'audio' => 'audio', 'video' => 'video', 'file' => 'document'];

    public static function eventId(array $payload, ?string $inboxId): ?string
    {
        $event = $payload['event'] ?? null;
        $id = $payload['id'] ?? null;

        if (! in_array($event, ['message_created', 'message_updated'], true) || $id === null) {
            return null;
        }
        if (($payload['private'] ?? false) || ! in_array($payload['message_type'] ?? null, ['incoming', 'outgoing'], true)) {
            return null;
        }
        if ($inboxId && (string) Arr::get($payload, 'inbox.id', Arr::get($payload, 'conversation.inbox_id')) !== (string) $inboxId) {
            return null;
        }

        return $event === 'message_updated'
            ? "message_updated:{$id}:".($payload['status'] ?? '')
            : "message_created:{$id}";
    }

    /** @return array<IncomingMessage|StatusUpdate> */
    public static function parse(array $payload): array
    {
        $id = (string) $payload['id'];

        if ($payload['event'] === 'message_updated') {
            $status = $payload['status'] ?? null;

            return in_array($status, ['sent', 'delivered', 'read', 'failed'], true) ? [new StatusUpdate($id, $status)] : [];
        }

        $direction = $payload['message_type'] === 'incoming' ? 'in' : 'out';
        $contact = $direction === 'in' && Arr::get($payload, 'sender.type') !== 'user'
            ? $payload['sender'] ?? []
            : Arr::get($payload, 'conversation.meta.sender', []);
        $phone = $contact['phone_number'] ?? null;
        if (! $phone) {
            return [];
        }

        $base = [
            'direction' => $direction,
            'phone' => $phone,
            'name' => $contact['name'] ?? null,
            'sentAt' => self::time($payload['created_at'] ?? null),
            'conversationId' => isset($payload['conversation']['id']) ? (string) $payload['conversation']['id'] : null,
            'raw' => $payload,
        ];

        $attachments = $payload['attachments'] ?? [];
        if ($attachments === []) {
            return [new IncomingMessage(...$base, providerMessageId: $id, type: 'text', body: $payload['content'] ?? null, attachment: null)];
        }

        $messages = [];
        foreach (array_values($attachments) as $i => $file) {
            $url = $file['data_url'] ?? null;
            if (! $url) {
                continue;
            }
            $messages[] = new IncomingMessage(
                ...$base,
                providerMessageId: $i === 0 ? $id : "{$id}:{$i}",
                type: self::TYPES[$file['file_type'] ?? ''] ?? 'document',
                body: $i === 0 ? ($payload['content'] ?? null) : null,
                attachment: new Attachment($url, $file['file_type'] ?? 'file', self::fileName($url)),
            );
        }

        return $messages;
    }

    private static function time(mixed $value): CarbonImmutable
    {
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        return $value ? CarbonImmutable::parse($value) : CarbonImmutable::now();
    }

    private static function fileName(string $url): ?string
    {
        $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));

        return $name !== '' ? $name : null;
    }
}
