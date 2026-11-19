<?php

namespace App\Modules\Imports;

use App\Modules\Leads\LeadIntake;
use App\Modules\Leads\PhoneNormalizer;

class ImportRunner
{
    public function __construct(private LeadIntake $intake) {}

    public function prepare(Import $import): Import
    {
        $sheet = SpreadsheetReader::read($import->file);
        $mapping = ColumnMapping::guess($sheet['headers']);

        $import->update([
            'headers' => $sheet['headers'],
            'mapping' => $mapping,
            'total_rows' => count($sheet['rows']),
            'status' => isset($mapping['phone']) ? 'pending' : 'needs_mapping',
        ]);

        return $import;
    }

    public function run(Import $import, array $mapping): Import
    {
        $sheet = SpreadsheetReader::read($import->file);
        $columns = array_flip(array_reverse($sheet['headers'], true));
        $value = fn (array $row, ?string $header) => $header !== null && isset($columns[$header]) ? ($row[$columns[$header]] ?? null) : null;

        $created = $duplicates = 0;
        $errors = [];

        foreach ($sheet['rows'] as $rowNumber => $row) {
            $rawPhone = SpreadsheetReader::text($value($row, $mapping['phone'] ?? null));
            $phone = PhoneNormalizer::normalize($rawPhone);
            if (! $phone) {
                $errors[] = ['row' => $rowNumber, 'message' => $rawPhone ? "Invalid phone number \"{$rawPhone}\"." : 'Phone number is missing.'];

                continue;
            }

            $fields = [];
            foreach ($mapping as $target => $header) {
                if (str_starts_with($target, 'field:')) {
                    $fields[substr($target, 6)] = $value($row, $header);
                }
            }

            $text = collect($sheet['headers'])
                ->map(fn ($header, $i) => ($cell = SpreadsheetReader::text($row[$i] ?? null)) !== null && $header !== '' ? "{$header}: {$cell}" : null)
                ->filter()
                ->join("\n");

            [$result] = $this->intake->upsert($phone, [
                'name' => SpreadsheetReader::text($value($row, $mapping['name'] ?? null)),
                'type' => SpreadsheetReader::text($value($row, $mapping['lead_type'] ?? null)),
                'branch' => SpreadsheetReader::text($value($row, $mapping['branch'] ?? null)),
                'fields' => $fields,
                'text' => $text,
            ], $import->source, $import);

            $result === 'created' ? $created++ : $duplicates++;
        }

        $import->update([
            'mapping' => $mapping,
            'status' => 'completed',
            'total_rows' => count($sheet['rows']),
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 200),
        ]);

        return $import;
    }
}
