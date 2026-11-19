<?php

namespace App\Modules\Imports;

use App\Models\User;
use App\Modules\Inbox\Message;
use App\Modules\Leads\Enums\LeadSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source', 'file', 'original_name', 'uploaded_by', 'sender_phone', 'message_id', 'mapping', 'headers',
    'status', 'total_rows', 'created_count', 'duplicate_count', 'error_count', 'errors',
])]
class Import extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'mapping' => 'array',
            'headers' => 'array',
            'errors' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function canRun(): bool
    {
        return in_array($this->status, ['pending', 'needs_mapping'], true);
    }
}
