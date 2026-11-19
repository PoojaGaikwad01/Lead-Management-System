<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'user_id', 'kind', 'body', 'call_outcome'])]
class LeadNote extends Model
{
    public const CALL_OUTCOMES = ['connected', 'no_answer', 'busy', 'wrong_number', 'call_back'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
