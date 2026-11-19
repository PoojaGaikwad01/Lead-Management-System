<?php

namespace App\Modules\Leads;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNormalizer
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $raw);
        if ($digits === '' || $digits === '+') {
            return null;
        }

        if (! str_starts_with($digits, '+') && strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = '+'.$digits;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse($digits, 'IN');
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }
}
