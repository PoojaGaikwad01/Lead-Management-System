<?php

namespace App\Modules\Inbox;

use App\Http\Controllers\Controller;
use App\Modules\Connector\WebhookEvent;
use App\Modules\Connector\WhatsAppManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/** Dev-only: fires Chatwoot-style webhook payloads through the mock connector. */
class SimulatorController extends Controller
{
    public function messages(): AnonymousResourceCollection
    {
        return MessageResource::collection(Message::with('contact')->latest('id')->limit(30)->get());
    }

    public function simulate(Request $request, WhatsAppManager $whatsapp): JsonResponse
    {
        abort_unless($whatsapp->activeName() === 'mock', 409, 'The simulator only works with WHATSAPP_PROVIDER=mock.');

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'required_without:file', 'string', 'max:4096'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $id = random_int(100000, 2_000_000_000);
        $sender = [
            'id' => crc32($data['phone']),
            'name' => $data['name'] ?? null,
            'phone_number' => $data['phone'],
            'identifier' => null,
            'thumbnail' => '',
            'type' => 'contact',
        ];

        $attachments = [];
        if ($file = $request->file('file')) {
            $name = $file->getClientOriginalName();
            $path = $file->storeAs('simulator/'.Str::uuid(), $name, 'local');
            $attachments[] = [
                'id' => random_int(1000, 999_999),
                'message_id' => $id,
                'file_type' => match (strtok((string) $file->getClientMimeType(), '/')) {
                    'image' => 'image', 'audio' => 'audio', 'video' => 'video', default => 'file',
                },
                'account_id' => 1,
                'extension' => $file->getClientOriginalExtension(),
                'data_url' => 'mock://'.dirname($path).'/'.rawurlencode($name),
                'thumb_url' => '',
                'file_size' => $file->getSize(),
            ];
        }

        $payload = [
            'event' => 'message_created',
            'id' => $id,
            'content' => $data['text'] ?? null,
            'content_type' => 'text',
            'content_attributes' => [],
            'message_type' => 'incoming',
            'private' => false,
            'source_id' => 'wamid.'.Str::upper(Str::random(40)),
            'created_at' => now()->toIso8601String(),
            'sender' => $sender,
            'attachments' => $attachments,
            'conversation' => [
                'id' => crc32('conv'.$data['phone']) % 100000,
                'inbox_id' => 1,
                'status' => 'open',
                'channel' => 'Channel::Whatsapp',
                'meta' => ['sender' => $sender],
            ],
            'inbox' => ['id' => 1, 'name' => 'Funky Monkey WhatsApp'],
            'account' => ['id' => 1, 'name' => 'Funky Monkey'],
        ];

        WebhookEvent::ingest('mock', $whatsapp->driver()->webhookEventId($payload), $payload);

        return response()->json(['data' => $payload], 202);
    }
}
