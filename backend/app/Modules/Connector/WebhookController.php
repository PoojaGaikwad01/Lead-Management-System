<?php

namespace App\Modules\Connector;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, WhatsAppManager $whatsapp): JsonResponse
    {
        abort_unless($provider === $whatsapp->activeName(), 404);

        $driver = $whatsapp->driver();
        abort_unless($driver->verifyWebhook($request), 401, 'Invalid webhook token.');

        $payload = $request->json()->all();
        $eventId = $driver->webhookEventId($payload);
        if ($eventId === null) {
            return response()->json(['status' => 'ignored']);
        }

        $queued = WebhookEvent::ingest($provider, $eventId, $payload);

        return response()->json(['status' => $queued ? 'queued' : 'duplicate']);
    }
}
