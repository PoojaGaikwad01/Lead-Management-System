<?php

namespace App\Modules\Integration;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['url', 'secret', 'events', 'active', 'last_delivery_at', 'last_status', 'last_error'])]
#[Hidden(['secret'])]
class WebhookSubscription extends Model
{
    public const EVENTS = ['lead.converted'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }
}
