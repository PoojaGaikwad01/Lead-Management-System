<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'color', 'sort', 'active', 'keywords'])]
class LeadType extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer', 'keywords' => 'array'];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(LeadTypeField::class)->orderBy('sort');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
