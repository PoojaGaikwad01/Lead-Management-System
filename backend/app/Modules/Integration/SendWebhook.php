<?php

namespace App\Modules\Integration;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** POSTs a signed JSON payload; retried with backoff when the receiver is down or returns non-2xx. */
class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $subscriptionId, public string $event, public array $data, public ?string $deliveryId = null)
    {
        $this->deliveryId ??= (string) Str::uuid();
    }

    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);
        if (! $subscription?->active) {
            return;
        }

        $status = self::deliver($subscription, $this->event, $this->data, $this->deliveryId);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Webhook {$subscription->url} answered HTTP {$status}.");
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Outgoing webhook failed after retries.', ['subscription_id' => $this->subscriptionId, 'event' => $this->event, 'error' => $e->getMessage()]);
    }

    /** Sends once and records the outcome; returns the HTTP status (0 when unreachable). */
    public static function deliver(WebhookSubscription $subscription, string $event, array $data, string $deliveryId): int
    {
        $body = json_encode([
            'event' => $event,
            'delivery_id' => $deliveryId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-FunkyMonkey-Event' => $event,
                    'X-FunkyMonkey-Delivery' => $deliveryId,
                    'X-FunkyMonkey-Signature' => 'sha256='.hash_hmac('sha256', $body, $subscription->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($subscription->url);
            $status = $response->status();
            $error = $response->successful() ? null : 'HTTP '.$status;
        } catch (Throwable $e) {
            $status = 0;
            $error = Str::limit($e->getMessage(), 250);
        }

        $subscription->update(['last_delivery_at' => now(), 'last_status' => $status ?: null, 'last_error' => $error]);

        return $status;
    }
}
