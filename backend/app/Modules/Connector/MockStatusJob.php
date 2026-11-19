<?php

namespace App\Modules\Connector;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Simulates Chatwoot's "message_updated" delivery receipts for the mock provider. */
class MockStatusJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $providerMessageId, public string $status) {}

    public function handle(): void
    {
        $payload = [
            'event' => 'message_updated',
            'id' => $this->providerMessageId,
            'message_type' => 'outgoing',
            'private' => false,
            'status' => $this->status,
        ];

        WebhookEvent::ingest('mock', "message_updated:{$this->providerMessageId}:{$this->status}", $payload);
    }
}
