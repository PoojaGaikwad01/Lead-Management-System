<?php

namespace App\Modules\Imports;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetReader
{
    /** @return array{headers: list<string>, rows: array<int, array<int, mixed>>} rows keyed by 1-based sheet row number */
    public static function read(string $path): array
    {
        $absolute = Storage::disk('local')->path($path);
        $reader = IOFactory::createReaderForFile($absolute);
        $reader->setReadDataOnly(true);
        $all = $reader->load($absolute)->getActiveSheet()->toArray(null, true, false, false);

        $filled = fn (array $row) => count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));
        $headerIndex = 0;
        foreach ($all as $i => $row) {
            if ($filled($row) >= 2) {
                $headerIndex = $i;
                break;
            }
        }

        $headers = array_map(fn ($h) => trim((string) $h), $all[$headerIndex] ?? []);
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        $rows = [];
        foreach (array_slice($all, $headerIndex + 1, null, true) as $i => $row) {
            if ($filled($row) > 0) {
                $rows[$i + 1] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
