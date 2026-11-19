<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Enums\ReplyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Inbox\Message;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'contact_id', 'lead_type_id', 'branch_id', 'source', 'assigned_to', 'assigned_by', 'assigned_at',
    'status', 'reply_status', 'reply_sent_at', 'reply_sent_by', 'reminder_count', 'last_reminder_at',
    'custom_data', 'lost_reason', 'last_message_at', 'unread_count', 'converted_at', 'closed_at',
    'import_id', 'source_text',
])]
class Lead extends Model
{
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'reply_status' => ReplyStatus::class,
            'custom_data' => 'array',
            'assigned_to' => 'integer',
            'branch_id' => 'integer',
            'assigned_at' => 'datetime',
            'reply_sent_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'last_message_at' => 'datetime',
            'converted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function leadType(): BelongsTo
    {
        return $this->belongsTo(LeadType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', [LeadStatus::Converted, LeadStatus::Lost, LeadStatus::Closed]);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->hasAdminAccess()
            || $this->assigned_to === $user->id
            || ($user->branch_id !== null && $this->branch_id === $user->branch_id);
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasAdminAccess()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id);
            if ($user->branch_id !== null) {
                $q->orWhere('branch_id', $user->branch_id);
            }
        });
    }
}
