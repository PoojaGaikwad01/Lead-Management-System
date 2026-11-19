<?php

namespace App\Modules\Connector;

use App\Modules\Connector\Data\StatusUpdate;
use App\Modules\Inbox\InboundMessageHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $eventId) {}

    public function handle(WhatsAppManager $whatsapp, InboundMessageHandler $handler): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event || $event->processed_at) {
            return;
        }

        $provider = $whatsapp->driver($event->provider);
        foreach ($provider->parseWebhook($event->payload) as $item) {
            $item instanceof StatusUpdate ? $handler->updateStatus($item) : $handler->receive($item, $provider);
        }

        $event->update(['processed_at' => now(), 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        WebhookEvent::whereKey($this->eventId)->update(['error' => $e->getMessage()]);
    }
}
