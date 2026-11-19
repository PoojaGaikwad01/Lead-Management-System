<?php

namespace App\Modules\Inbox;

use App\Modules\Leads\Models\Contact;
use App\Modules\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id', 'contact_id', 'direction', 'type', 'body', 'media_url', 'media_mime', 'media_name',
    'provider_message_id', 'status', 'sent_at', 'read_at', 'raw', 'is_agency',
])]
class Message extends Model
{
    public const STATUS_RANK = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'raw' => 'array',
            'is_agency' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public static function typeForMime(string $mime): string
    {
        return match (strtok($mime, '/')) {
            'image' => 'image',
            'audio' => 'audio',
            'video' => 'video',
            default => 'document',
        };
    }
}
