<?php

namespace App\Modules\Leads;

use App\Modules\Leads\Models\LeadType;
use App\Modules\Leads\Models\LeadTypeField;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/** Maps loosely-labelled values ("Kids", "Party date") onto a lead type's custom fields. */
class FieldValues
{
    /** @param array<string, mixed> $raw label => value */
    public static function map(?LeadType $type, array $raw): array
    {
        if (! $type || $raw === []) {
            return [];
        }

        $values = [];
        foreach ($raw as $label => $value) {
            $field = self::match($type, (string) $label);
            $clean = $field ? self::clean($field, $value) : null;
            if ($field && $clean !== null && ! isset($values[$field->key])) {
                $values[$field->key] = $clean;
            }
        }

        return $values;
    }

    public static function clean(LeadTypeField $field, mixed $value): string|int|float|null
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return match ($field->field_type) {
            'number' => self::number($value),
            'date' => self::date($value),
            default => trim((string) $value),
        };
    }

    private static function match(LeadType $type, string $label): ?LeadTypeField
    {
        $label = Str::snake(Str::lower(trim($label)));
        if ($label === '') {
            return null;
        }

        return $type->fields->first(function (LeadTypeField $field) use ($label) {
            $key = $field->key;
            $fieldLabel = Str::snake(Str::lower($field->label));

            return $label === $key || $label === $fieldLabel
                || str_contains($key, $label) || str_contains($fieldLabel, $label)
                || str_contains($label, $key);
        });
    }

    private static function number(mixed $value): int|float|string
    {
        if (is_numeric($value)) {
            return $value + 0;
        }

        return preg_match('/\d+(\.\d+)?/', (string) $value, $m) ? $m[0] + 0 : trim((string) $value);
    }

    private static function date(mixed $value): string
    {
        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y', 'd-m-y', 'd M Y', 'd F Y', 'j M Y', 'j F Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return $value;
        }
    }
}
