<?php

namespace App\Modules\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Connector\WhatsAppManager;
use App\Modules\Imports\ColumnMapping;
use App\Modules\Leads\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingsController extends Controller
{
    private const KEYS = ['agency_numbers', 'reminder_rules', 'ai_extraction_enabled', 'import_column_mapping'];

    public function index(WhatsAppManager $whatsapp): JsonResponse
    {
        Gate::authorize('manage-settings');
        $provider = $whatsapp->activeName();

        return response()->json(['data' => [
            'agency_numbers' => Setting::get('agency_numbers', []),
            'reminder_rules' => Setting::reminderRules(),
            'ai_extraction_enabled' => (bool) Setting::get('ai_extraction_enabled', config('lms.ai.enabled')),
            'import_column_mapping' => (object) Setting::get('import_column_mapping', []),
            'mapping_targets' => ColumnMapping::targets(),
            'status' => [
                'whatsapp_provider' => $provider,
                'provider_configured' => match ($provider) {
                    'chatwoot' => collect(config('whatsapp.chatwoot'))->every(fn ($value) => filled($value)),
                    'periskope' => filled(config('whatsapp.periskope.api_key')),
                    default => true,
                },
                'webhook_url' => url("/api/webhooks/whatsapp/{$provider}").($provider === 'mock' ? '' : '?token=<'.strtoupper($provider).'_WEBHOOK_TOKEN>'),
                'ai_key_configured' => filled(config('lms.ai.api_key')),
                'ai_model' => config('lms.ai.model'),
            ],
        ]]);
    }

    public function show(string $key): JsonResponse
    {
        Gate::authorize('manage-settings');
        abort_unless(in_array($key, self::KEYS, true), 404);

        return response()->json(['data' => Setting::get($key, [])]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        Gate::authorize('manage-settings');
        abort_unless(in_array($key, self::KEYS, true), 404);

        $value = match ($key) {
            'agency_numbers' => $this->agencyNumbers($request),
            'reminder_rules' => $request->validate([
                'value.auto_enabled' => ['required', 'boolean'],
                'value.after_hours' => ['required', 'integer', 'min:1', 'max:168'],
                'value.whatsapp_alert' => ['required', 'boolean'],
            ], [], ['value.after_hours' => 'hours'])['value'],
            'ai_extraction_enabled' => $request->validate(['value' => ['required', 'boolean']])['value'],
            'import_column_mapping' => $this->columnMapping($request),
        };
        Setting::put($key, $value);

        return response()->json(['data' => $value]);
    }

    public function checkProvider(WhatsAppManager $whatsapp): JsonResponse
    {
        Gate::authorize('manage-settings');

        try {
            return response()->json(['message' => $whatsapp->driver()->checkConnection()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Connection failed: '.$e->getMessage()], 422);
        }
    }

    private function agencyNumbers(Request $request): array
    {
        $request->validate(['value' => ['present', 'array'], 'value.*' => ['string', 'max:30']]);

        $numbers = [];
        foreach ($request->input('value') as $i => $raw) {
            $phone = PhoneNormalizer::normalize($raw);
            if (! $phone) {
                throw ValidationException::withMessages(["value.{$i}" => "\"{$raw}\" is not a valid phone number."]);
            }
            $numbers[] = $phone;
        }

        return array_values(array_unique($numbers));
    }

    private function columnMapping(Request $request): array
    {
        $data = $request->validate([
            'value' => ['present', 'array'],
            'value.*' => ['nullable', 'string', 'max:100'],
        ]);
        $keys = ColumnMapping::keys();
        foreach (array_keys($data['value']) as $key) {
            if (! in_array($key, $keys, true)) {
                throw ValidationException::withMessages(['value' => "Unknown import field \"{$key}\"."]);
            }
        }

        return array_filter(array_map(fn ($h) => is_string($h) ? trim($h) : null, $data['value']), fn ($h) => filled($h));
    }
}
