<?php

namespace App\Modules\Integration;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Super-admin management of portal API clients and outgoing webhooks. */
class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('manage-settings');

        return response()->json(['data' => [
            'clients' => ApiClient::with('tokens')->latest('id')->get()->map(fn (ApiClient $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'active' => $c->active,
                'created_at' => $c->created_at?->toIso8601String(),
                'last_used_at' => $c->tokens->max('last_used_at')?->toIso8601String(),
            ]),
            'webhooks' => WebhookSubscription::latest('id')->get()->map(fn (WebhookSubscription $w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events,
                'active' => $w->active,
                'last_delivery_at' => $w->last_delivery_at?->toIso8601String(),
                'last_status' => $w->last_status,
                'last_error' => $w->last_error,
            ]),
            'api_base_url' => url('/api/v1'),
        ]]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $client = ApiClient::create(['name' => $data['name'], 'active' => true]);
        $token = $client->createToken('portal', [ApiClient::ABILITY])->plainTextToken;

        return response()->json(['data' => ['id' => $client->id, 'name' => $client->name, 'token' => $token]], 201);
    }

    public function destroyClient(ApiClient $client): Response
    {
        Gate::authorize('manage-settings');
        $client->tokens()->delete();
        $client->delete();

        return response()->noContent();
    }

    public function storeWebhook(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate([
            'url' => ['required', 'url:https,http', 'max:255'],
            'events' => ['array'],
            'events.*' => [Rule::in(WebhookSubscription::EVENTS)],
        ]);

        $secret = Str::random(40);
        $webhook = WebhookSubscription::create([
            'url' => $data['url'],
            'secret' => $secret,
            'events' => $data['events'] ?? WebhookSubscription::EVENTS,
            'active' => true,
        ]);

        return response()->json(['data' => ['id' => $webhook->id, 'url' => $webhook->url, 'secret' => $secret]], 201);
    }

    public function updateWebhook(Request $request, WebhookSubscription $webhook): Response
    {
        Gate::authorize('manage-settings');
        $webhook->update($request->validate(['active' => ['required', 'boolean']]));

        return response()->noContent();
    }

    public function destroyWebhook(WebhookSubscription $webhook): Response
    {
        Gate::authorize('manage-settings');
        $webhook->delete();

        return response()->noContent();
    }

    /** Sends a sample lead.converted payload right away so the portal team can check their receiver. */
    public function testWebhook(WebhookSubscription $webhook): JsonResponse
    {
        Gate::authorize('manage-settings');

        $lead = Lead::with(['contact', 'leadType', 'branch', 'assignee'])->latest('id')->first();
        $sample = $lead ? (new PortalLeadResource($lead))->resolve() : ['id' => 0, 'status' => 'converted', 'customer' => ['name' => 'Test Customer', 'phone' => '+919999999999']];
        $status = SendWebhook::deliver($webhook, 'lead.converted', $sample, 'test-'.Str::uuid());

        return response()->json([
            'ok' => $status >= 200 && $status < 300,
            'status' => $status ?: null,
            'error' => $webhook->fresh()->last_error,
        ]);
    }
}
