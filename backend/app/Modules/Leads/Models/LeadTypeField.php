<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_type_id', 'key', 'label', 'field_type', 'options', 'required', 'sort'])]
class LeadTypeField extends Model
{
    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'sort' => 'integer'];
    }

    public function leadType(): BelongsTo
    {
        return $this->belongsTo(LeadType::class);
    }
}
