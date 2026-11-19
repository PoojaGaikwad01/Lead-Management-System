<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'name', 'provider_conversation_id'])]
class Contact extends Model
{
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
