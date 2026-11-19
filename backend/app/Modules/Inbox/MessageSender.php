<?php

namespace App\Modules\Inbox;

use App\Modules\Connector\WhatsAppManager;
use App\Modules\Leads\Models\Lead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageSender
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    public function send(Lead $lead, ?string $text, ?UploadedFile $file): Message
    {
        $media = [];
        if ($file) {
            $media = [
                'media_url' => $file->store('media/'.now()->format('Y/m'), 'local'),
                'media_mime' => $file->getMimeType(),
                'media_name' => $file->getClientOriginalName(),
            ];
        }

        $message = Message::create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'direction' => 'out',
            'type' => $file ? Message::typeForMime((string) $media['media_mime']) : 'text',
            'body' => $text,
            'status' => 'pending',
            'sent_at' => now(),
            ...$media,
        ]);

        try {
            $provider = $this->whatsapp->driver();
            $sent = $file
                ? $provider->sendMedia($lead->contact, $media['media_url'], $media['media_name'], $text)
                : $provider->sendText($lead->contact, (string) $text);
            $message->update(['provider_message_id' => $sent->providerMessageId, 'status' => $sent->status]);
        } catch (Throwable $e) {
            Log::error('WhatsApp send failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            $message->update(['status' => 'failed']);
        }

        $lead->update(['last_message_at' => $message->sent_at]);
        MessageEvent::send($message, 'message.created');

        return $message;
    }
}
