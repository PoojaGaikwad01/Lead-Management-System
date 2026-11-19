<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'sent_by', 'to_user_id', 'channel', 'auto'])]
class Reminder extends Model
{
    protected function casts(): array
    {
        return ['auto' => 'boolean'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
