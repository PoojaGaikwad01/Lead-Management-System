<?php

namespace App\Modules\Connector;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

#[Fillable(['provider', 'event_id', 'payload', 'processed_at', 'error'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }

    /** Stores the event and queues processing; false when this event was already received. */
    public static function ingest(string $provider, string $eventId, array $payload): bool
    {
        try {
            $event = static::create(['provider' => $provider, 'event_id' => $eventId, 'payload' => $payload]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        ProcessWebhookEvent::dispatch($event->id);

        return true;
    }
}
