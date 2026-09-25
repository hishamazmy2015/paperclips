<?php

declare(strict_types=1);

namespace App\Provisioning;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** E.164 normalisation with the UAE as the default region (spec §12.1). */
final class PhoneNormalizer
{
    public function __construct(private readonly PhoneNumberUtil $util) {}

    public static function make(): self
    {
        return new self(PhoneNumberUtil::getInstance());
    }

    /** Returns +E.164 or null when the number is not valid anywhere. */
    public function normalize(string $raw, ?string $defaultRegion = null): ?string
    {
        $defaultRegion ??= (string) config('platform.phone_default_region', 'AE');
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // "00" international prefix and Arabic-Indic digits are common in pasted numbers.
        $raw = strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if (str_starts_with($raw, '00')) {
            $raw = '+'.substr($raw, 2);
        }

        try {
            $number = $this->util->parse($raw, $defaultRegion);
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->util->isValidNumber($number)) {
            return null;
        }

        return $this->util->format($number, PhoneNumberFormat::E164);
    }
}
