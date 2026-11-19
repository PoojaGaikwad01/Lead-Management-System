<?php

namespace App\Modules\Inbox;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'contact_id' => $this->contact_id,
            'direction' => $this->direction,
            'type' => $this->type,
            'body' => $this->body,
            'status' => $this->status,
            'is_agency' => $this->is_agency,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'media' => $this->media_url || $this->media_name ? [
                'name' => $this->media_name,
                'mime' => $this->media_mime,
                'url' => $this->media_url ? "/api/messages/{$this->id}/media" : null,
            ] : null,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'phone' => $this->contact->phone,
                'name' => $this->contact->name,
            ]),
        ];
    }
}
