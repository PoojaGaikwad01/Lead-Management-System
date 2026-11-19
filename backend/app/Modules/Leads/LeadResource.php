<?php

namespace App\Modules\Leads;

use App\Modules\Leads\Enums\ReplyStatus;
use App\Modules\Leads\Models\Lead;
use App\Modules\Settings\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $replyDueAt = $this->reply_status === ReplyStatus::NotSent && $this->assigned_at
            ? $this->assigned_at->copy()->addHours((int) Setting::reminderRules()['after_hours'])
            : null;

        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'reply_status' => $this->reply_status,
            'unread_count' => $this->unread_count,
            'reminder_count' => $this->reminder_count,
            'custom_data' => (object) ($this->custom_data ?? []),
            'lost_reason' => $this->lost_reason,
            'source_text' => $this->source_text,
            'import_id' => $this->import_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'reply_sent_at' => $this->reply_sent_at?->toIso8601String(),
            'last_reminder_at' => $this->last_reminder_at?->toIso8601String(),
            'reply_due_at' => $replyDueAt?->toIso8601String(),
            'contact' => ['id' => $this->contact->id, 'name' => $this->contact->name, 'phone' => $this->contact->phone],
            'lead_type' => $this->leadType ? ['id' => $this->leadType->id, 'name' => $this->leadType->name, 'color' => $this->leadType->color] : null,
            'branch' => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null,
            'assignee' => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null,
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'type' => $this->latestMessage->type,
                'body' => Str::limit((string) $this->latestMessage->body, 80),
                'direction' => $this->latestMessage->direction,
                'status' => $this->latestMessage->status,
                'media_name' => $this->latestMessage->media_name,
                'sent_at' => $this->latestMessage->sent_at?->toIso8601String(),
            ] : null),
        ];
    }
}
