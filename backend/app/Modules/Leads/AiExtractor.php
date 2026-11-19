<?php

namespace App\Modules\Leads;

use Anthropic\Client;
use App\Modules\Branches\Branch;
use App\Modules\Leads\Models\LeadType;
use App\Modules\Settings\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Optional Claude extraction. Every failure returns null so callers keep the keyword-parser result. */
class AiExtractor
{
    public static function enabled(): bool
    {
        return (bool) Setting::get('ai_extraction_enabled', config('lms.ai.enabled')) && filled(config('lms.ai.api_key'));
    }

    /** @return list<array{name: ?string, phone: ?string, type: ?string, branch: ?string, fields: array<string, string>}>|null */
    public function extract(string $text): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $types = LeadType::with('fields')->where('active', true)->orderBy('sort')->get();
        $typeList = $types->map(fn (LeadType $t) => '- '.$t->name.($t->fields->isEmpty() ? '' : ' (fields: '.$t->fields->map(fn ($f) => "{$f->key}: {$f->label}, {$f->field_type}")->join('; ').')'))->join("\n");
        $branches = Branch::where('active', true)->pluck('name')->join(', ') ?: 'none configured';

        try {
            $message = (new Client(apiKey: config('lms.ai.api_key')))->messages->create(
                model: config('lms.ai.model'),
                maxTokens: 4000,
                system: "You extract sales leads for Funky Monkey, a kids' play zone, from WhatsApp messages. "
                    ."Return one entry per distinct customer. Use null when a value is not stated; never invent phone numbers.\n"
                    ."Lead types:\n{$typeList}\nBranches: {$branches}\n"
                    .'Use the exact lead type and branch names above. Dates as YYYY-MM-DD.',
                messages: [['role' => 'user', 'content' => $text]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
            );

            if ($message->stopReason !== 'end_turn') {
                Log::warning('AI lead extraction stopped early.', ['stop_reason' => $message->stopReason]);

                return null;
            }

            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $data = json_decode($block->text, true);

                    return is_array($data['leads'] ?? null) ? array_map(self::normalise(...), $data['leads']) : null;
                }
            }
        } catch (Throwable $e) {
            Log::warning('AI lead extraction failed; using keyword parsing.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private static function normalise(array $lead): array
    {
        $fields = [];
        foreach ($lead['fields'] ?? [] as $pair) {
            if (filled($pair['key'] ?? null) && filled($pair['value'] ?? null)) {
                $fields[$pair['key']] = (string) $pair['value'];
            }
        }

        return [
            'name' => $lead['name'] ?? null,
            'phone' => $lead['phone'] ?? null,
            'type' => $lead['lead_type'] ?? null,
            'branch' => $lead['branch'] ?? null,
            'fields' => $fields,
        ];
    }

    private static function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'leads' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => $nullableString,
                            'phone' => $nullableString,
                            'lead_type' => $nullableString,
                            'branch' => $nullableString,
                            'fields' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string']],
                                    'required' => ['key', 'value'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['name', 'phone', 'lead_type', 'branch', 'fields'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['leads'],
            'additionalProperties' => false,
        ];
    }
}
