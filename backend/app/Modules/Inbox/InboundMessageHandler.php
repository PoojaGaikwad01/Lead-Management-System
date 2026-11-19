<?php

namespace App\Modules\Inbox;

use App\Models\User;
use App\Modules\Connector\Data\Attachment;
use App\Modules\Connector\Data\IncomingMessage;
use App\Modules\Connector\Data\StatusUpdate;
use App\Modules\Connector\WhatsAppProvider;
use App\Modules\Imports\AgencyMessageProcessor;
use App\Modules\Leads\LeadCapture;
use App\Modules\Leads\LeadEnricher;
use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\PhoneNormalizer;
use App\Modules\Settings\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class InboundMessageHandler
{
    public function receive(IncomingMessage $in, WhatsAppProvider $provider): ?Message
    {
        if (Message::where('provider_message_id', $in->providerMessageId)->exists()) {
            return null;
        }

        $phone = PhoneNormalizer::normalize($in->phone);
        if (! $phone) {
            Log::warning('WhatsApp message ignored: invalid phone number.', ['phone' => $in->phone, 'id' => $in->providerMessageId]);

            return null;
        }

        $media = $in->attachment ? $this->storeMedia($in->attachment, $provider) : [];

        try {
            $message = DB::transaction(function () use ($in, $phone, $media) {
                $contact = Contact::firstOrCreate(['phone' => $phone], ['name' => $in->name]);
                $contact->fill(array_filter([
                    'name' => $contact->name ?: $in->name,
                    'provider_conversation_id' => $in->conversationId,
                ]))->save();

                $isAgency = Setting::isAgencyNumber($phone);
                $isStaff = ! $isAgency && User::where('phone', $phone)->exists();
                $lead = $isAgency || $isStaff ? null : LeadCapture::leadFor($contact);

                $message = Message::create([
                    'lead_id' => $lead?->id,
                    'contact_id' => $contact->id,
                    'direction' => $in->direction,
                    'type' => $in->type,
                    'body' => $in->body,
                    'provider_message_id' => $in->providerMessageId,
                    'status' => $in->direction === 'in' ? 'received' : 'sent',
                    'sent_at' => $in->sentAt,
                    'raw' => $in->raw,
                    'is_agency' => $isAgency,
                    ...$media,
                ]);

                if ($lead) {
                    $lead->last_message_at = $in->sentAt;
                    if ($in->direction === 'in') {
                        $lead->unread_count++;
                    }
                    $lead->save();
                }

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($message->direction === 'in') {
            if ($message->is_agency) {
                rescue(fn () => app(AgencyMessageProcessor::class)->process($message));
            } elseif ($message->lead_id && filled($message->body)) {
                rescue(fn () => app(LeadEnricher::class)->enrich($message->lead, (string) $message->body));
            }
        }

        MessageEvent::send($message, 'message.created');

        return $message;
    }

    public function updateStatus(StatusUpdate $update): void
    {
        $message = Message::where('provider_message_id', $update->providerMessageId)->first();
        if (! $message) {
            return;
        }

        $rank = Message::STATUS_RANK;
        $isUpgrade = ($rank[$update->status] ?? -1) > ($rank[$message->status] ?? -1);
        if ($update->status !== 'failed' && ! $isUpgrade) {
            return;
        }

        $message->update(['status' => $update->status]);
        MessageEvent::send($message, 'message.updated');
    }

    private function storeMedia(Attachment $attachment, WhatsAppProvider $provider): array
    {
        try {
            $content = $provider->downloadMedia($attachment->url);
        } catch (Throwable $e) {
            Log::error('WhatsApp media download failed.', ['url' => $attachment->url, 'error' => $e->getMessage()]);

            return ['media_name' => $attachment->name];
        }

        $mimeTypes = MimeTypes::getDefault();
        $extension = strtolower(pathinfo((string) $attachment->name, PATHINFO_EXTENSION));
        $mime = ($extension ? $mimeTypes->getMimeTypes($extension)[0] ?? null : null)
            ?? (new \finfo(FILEINFO_MIME_TYPE))->buffer($content)
            ?: 'application/octet-stream';
        $extension = $extension ?: ($mimeTypes->getExtensions($mime)[0] ?? 'bin');

        $path = 'media/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $content);

        return ['media_url' => $path, 'media_mime' => $mime, 'media_name' => $attachment->name ?? basename($path)];
    }
}
