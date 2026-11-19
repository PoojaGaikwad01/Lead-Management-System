<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'user_id', 'action', 'details'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(Lead $lead, ?User $user, string $action, array $details = []): self
    {
        return static::create(['lead_id' => $lead->id, 'user_id' => $user?->id, 'action' => $action, 'details' => $details ?: null]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
