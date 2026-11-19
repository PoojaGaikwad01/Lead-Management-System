<?php

namespace App\Modules\Imports;

use App\Modules\Leads\AgencyTextParser;
use App\Modules\Leads\Models\LeadType;
use App\Modules\Settings\Setting;
use Illuminate\Support\Str;

/** Import targets (name, phone, type, branch, custom fields) and header auto-mapping. */
class ColumnMapping
{
    private const BASE = ['name' => 'Name', 'phone' => 'Phone', 'lead_type' => 'Lead type', 'branch' => 'Branch'];

    private const SYNONYMS = ['name' => 'name', 'phone' => 'phone', 'lead_type' => 'type', 'branch' => 'branch'];

    /** @return list<array{key: string, label: string, required: bool}> */
    public static function targets(): array
    {
        $targets = [];
        foreach (self::BASE as $key => $label) {
            $targets[$key] = ['key' => $key, 'label' => $label, 'required' => $key === 'phone'];
        }

        foreach (LeadType::with('fields')->orderBy('sort')->get() as $type) {
            foreach ($type->fields as $field) {
                $key = "field:{$field->key}";
                $targets[$key] ??= ['key' => $key, 'label' => $field->label, 'required' => false, 'types' => []];
                $targets[$key]['types'][] = $type->name;
            }
        }

        return array_values(array_map(function (array $t) {
            if (isset($t['types'])) {
                $t['label'] .= ' ('.implode(', ', $t['types']).')';
                unset($t['types']);
            }

            return $t;
        }, $targets));
    }

    public static function keys(): array
    {
        return array_column(self::targets(), 'key');
    }

    /** Saved mapping where its headers exist, then synonym matches for the rest. */
    public static function guess(array $headers): array
    {
        $mapping = array_filter(
            Setting::get('import_column_mapping', []),
            fn ($header, $key) => in_array($header, $headers, true) && in_array($key, self::keys(), true),
            ARRAY_FILTER_USE_BOTH,
        );

        $normalized = array_map(fn ($h) => Str::snake(Str::lower($h)), $headers);
        foreach (self::targets() as $target) {
            $key = $target['key'];
            if (isset($mapping[$key])) {
                continue;
            }

            $candidates = str_starts_with($key, 'field:')
                ? [substr($key, 6), Str::snake(Str::lower(Str::before($target['label'], ' (')))]
                : array_map(fn ($s) => Str::snake($s), AgencyTextParser::LABELS[self::SYNONYMS[$key]]);

            $isField = str_starts_with($key, 'field:');
            foreach ($normalized as $i => $header) {
                $matches = in_array($header, $candidates, true) || ($isField && strlen($header) >= 3 && collect($candidates)
                    ->contains(fn ($c) => str_contains($c, $header) || str_contains($header, $c)));
                if ($header !== '' && $matches && ! in_array($headers[$i], $mapping, true)) {
                    $mapping[$key] = $headers[$i];
                    break;
                }
            }
        }

        return $mapping;
    }
}
