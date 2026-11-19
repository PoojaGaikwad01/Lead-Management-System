<?php

namespace App\Modules\Leads;

use Illuminate\Support\Str;

/**
 * Splits an agency WhatsApp text into leads.
 * Fixed format: "Label: value" lines, one block per lead (blank line or repeated label starts the next).
 * Fallback: one lead per phone number found, with the name taken from the same line.
 */
class AgencyTextParser
{
    public const LABELS = [
        'name' => ['name', 'full name', 'customer', 'customer name','client', 'client name', 'parent', 'parent name', 'contact name', 'lead name'],
        'phone' => ['phone', 'phone no', 'phone number', 'mobile', 'mobile no', 'mobile number', 'mob', 'contact', 'contact no', 'contact number', 'number', 'whatsapp', 'whatsapp no', 'whatsapp number', 'ph', 'cell'],
        'type' => ['type', 'lead type', 'enquiry', 'enquiry type', 'enquiry for', 'inquiry', 'interest', 'interested in', 'category', 'service', 'requirement', 'package'],
        'branch' => ['branch', 'location', 'centre', 'center', 'outlet', 'store', 'area', 'preferred branch'],
    ];

    private const PHONE = '/(?<!\d)(?:\+?\s?91[\s-]?|0)?[6-9]\d{2}[\s-]?\d{2}[\s-]?\d{2}[\s-]?\d{3}(?!\d)/';

    /** @return list<array{name: ?string, phone: ?string, type: ?string, branch: ?string, fields: array<string, string>, text: string}> */
    public function parse(string $text): array
    {
        $leads = $this->parseFixedFormat($text);

        return $leads !== [] ? $leads : $this->parseFreeText($text);
    }

    public function isFixedFormat(string $text): bool
    {
        return $this->parseFixedFormat($text) !== [];
    }

    private function parseFixedFormat(string $text): array
    {
        $leads = [];
        $current = $this->blank();

        $flush = function () use (&$leads, &$current) {
            if ($current['phone'] || $current['name']) {
                $current['text'] = trim($current['text']);
                $leads[] = $current;
            }
            $current = $this->blank();
        };

        foreach (preg_split('/\R/', str_replace("\r", '', $text)) as $line) {
            $line = trim($line, " \t*_•-");
            if ($line === '' || preg_match('/^[-=*_]{3,}$/', $line)) {
                $flush();

                continue;
            }

            if (! preg_match('/^([\p{L}][\p{L}\s\'\/.]{0,30}?)\s*[:=]\s*(.+)$/u', $line, $m)) {
                $current['text'] .= $line."\n";

                continue;
            }

            [$label, $value] = [Str::lower(trim($m[1], ' .')), trim($m[2])];
            $target = $this->target($label);

            if ($target && $current[$target] !== null) {
                $flush();
            }

            if ($target === 'phone') {
                $current['phone'] = $value;
            } elseif ($target) {
                $current[$target] = $value;
            } else {
                $current['fields'][$m[1]] = $value;
            }
            $current['text'] .= $line."\n";
        }
        $flush();

        return array_values(array_filter($leads, fn ($lead) => $lead['phone'] !== null));
    }

    private function parseFreeText(string $text): array
    {
        $lines = preg_split('/\R/', $text);
        $matches = [];
        foreach ($lines as $line) {
            if (preg_match_all(self::PHONE, $line, $found)) {
                foreach ($found[0] as $phone) {
                    $matches[] = [$phone, $line];
                }
            }
        }

        return array_map(function (array $match) use ($matches, $text) {
            [$phone, $line] = $match;
            $context = count($matches) === 1 ? $text : $line;
            $before = trim(Str::before($line, $phone), " \t:-,|");

            return [...$this->blank(), 'name' => $this->nameFrom($before), 'phone' => $phone, 'text' => trim($context)];
        }, $matches);
    }

    /** "Please call Amit Kulkarni" -> "Amit Kulkarni"; "rahul sharma" -> "rahul sharma". */
    private function nameFrom(string $before): ?string
    {
        if ($before === '' || ! preg_match('/^[\p{L}][\p{L}\s.\']*$/u', $before)) {
            return null;
        }

        $words = preg_split('/\s+/', $before);
        $capitalised = [];
        for ($i = count($words) - 1; $i >= 0 && preg_match('/^\p{Lu}[\p{L}.\']*$/u', $words[$i]); $i--) {
            array_unshift($capitalised, $words[$i]);
        }

        if ($capitalised !== []) {
            return implode(' ', array_slice($capitalised, -4));
        }

        return count($words) <= 3 ? $before : null;
    }

    private function target(string $label): ?string
    {
        foreach (self::LABELS as $target => $labels) {
            if (in_array($label, $labels, true)) {
                return $target;
            }
        }

        return null;
    }

    private function blank(): array
    {
        return ['name' => null, 'phone' => null, 'type' => null, 'branch' => null, 'fields' => [], 'text' => ''];
    }
}
