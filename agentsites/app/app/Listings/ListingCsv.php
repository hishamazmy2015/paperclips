<?php

declare(strict_types=1);

namespace App\Listings;

use InvalidArgumentException;

/**
 * The listings CSV contract (spec §14): samples/listings.csv is the template. `media` holds
 * `;`-separated image URLs. Values are validated per row; the row number (2 = first data row)
 * travels with every error.
 */
final class ListingCsv
{
    /** @var list<string> */
    public const COLUMNS = ['ref', 'title_en', 'title_ar', 'offering', 'property_type', 'price', 'currency', 'bedrooms', 'bathrooms', 'area_sqft', 'community', 'city', 'status', 'featured', 'description_en', 'description_ar', 'media'];

    /** @var list<string> */
    public const REQUIRED = ['ref', 'title_en', 'offering', 'property_type', 'price'];

    /** @var list<string> */
    public const TYPES = ['apartment', 'villa', 'townhouse', 'penthouse', 'studio', 'land', 'office', 'duplex', 'compound', 'retail', 'warehouse', 'other'];

    /** @var list<string> */
    public const STATUSES = ['available', 'sold', 'rented', 'hidden'];

    public static function template(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidArgumentException('cannot open temp');
        }
        fputcsv($handle, self::COLUMNS, escape: '');
        fputcsv($handle, ['REF-1001', '2BR with Burj view in Downtown', 'شقة غرفتين بإطلالة على برج خليفة', 'sale', 'apartment', '2450000', 'AED', '2', '2', '1350', 'Downtown', 'Dubai', 'available', 'true', 'Bright two-bedroom apartment with a full Burj Khalifa view.', 'شقة مشرقة بغرفتي نوم وإطلالة كاملة على برج خليفة.', 'https://example.com/photos/1001-1.jpg;https://example.com/photos/1001-2.jpg'], escape: '');
        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    /**
     * @return array<int, array<string, string>> row number => row (only the known columns)
     *
     * @throws InvalidArgumentException when the file or its header is unusable
     */
    public static function rows(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("cannot read {$path}");
        }
        $header = fgetcsv($handle, escape: '');
        if ($header === false || $header === [null]) {
            throw new InvalidArgumentException('empty file');
        }
        $header = array_map(static fn (mixed $h): string => strtolower(trim((string) $h, " \t\xEF\xBB\xBF")), $header);
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            throw new InvalidArgumentException('missing columns: '.implode(', ', $missing));
        }

        $rows = [];
        $number = 1;
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            $number++;
            if ($line === [null] || implode('', array_map('strval', $line)) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $column) {
                if (in_array($column, self::COLUMNS, true)) {
                    $row[$column] = trim((string) ($line[$i] ?? ''));
                }
            }
            $rows[$number] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Validate and normalise one row into listing attributes (without tenant/source).
     *
     * @param  array<string, mixed>  $row
     * @return array{attributes: array<string, mixed>, media: list<string>, errors: list<string>}
     */
    public static function normalize(array $row): array
    {
        $errors = [];
        $get = static fn (string $key): string => trim((string) ($row[$key] ?? ''));

        $ref = $get('ref');
        if ($ref === '' || mb_strlen($ref) > 80 || preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\/-]*$/u', $ref) !== 1) {
            $errors[] = 'ref: required, up to 80 letters, digits, spaces, . _ / -';
        }
        $titleEn = $get('title_en');
        if ($titleEn === '' || mb_strlen($titleEn) > 200) {
            $errors[] = 'title_en: required, up to 200 characters';
        }
        $offering = strtolower($get('offering'));
        $offering = match ($offering) {
            'sale', 'sell', 'buy', 'for sale' => 'sale',
            'rent', 'rental', 'for rent', 'lease' => 'rent',
            default => $offering,
        };
        if (! in_array($offering, ['sale', 'rent'], true)) {
            $errors[] = 'offering: sale or rent';
        }
        $type = strtolower($get('property_type'));
        if (! in_array($type, self::TYPES, true)) {
            $errors[] = 'property_type: one of '.implode(', ', self::TYPES);
        }
        $price = (float) str_replace([',', ' '], '', $get('price'));
        if ($price <= 0 || $price > 999999999999) {
            $errors[] = 'price: a positive number';
        }
        $currency = strtoupper($get('currency')) ?: 'AED';
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors[] = 'currency: a 3-letter code';
        }
        $ints = [];
        foreach (['bedrooms', 'bathrooms'] as $key) {
            $value = $get($key);
            if ($value === '') {
                $ints[$key] = null;
            } elseif (! is_numeric($value) || (int) $value < 0 || (int) $value > 30) {
                $errors[] = $key.': 0–30';
            } else {
                $ints[$key] = (int) $value;
            }
        }
        $sqft = $get('area_sqft') === '' ? null : (float) str_replace([',', ' '], '', $get('area_sqft'));
        if ($sqft !== null && ($sqft <= 0 || $sqft > 10000000)) {
            $errors[] = 'area_sqft: a positive number';
        }
        $status = strtolower($get('status')) ?: 'available';
        if (! in_array($status, self::STATUSES, true)) {
            $errors[] = 'status: one of '.implode(', ', self::STATUSES);
        }
        $featured = in_array(strtolower($get('featured')), ['1', 'true', 'yes', 'y'], true);

        $media = [];
        foreach (preg_split('/[;\n]/', $get('media')) ?: [] as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $url) !== 1) {
                $errors[] = 'media: URLs must start with http(s)://';
                break;
            }
            $media[] = $url;
        }

        return [
            'attributes' => [
                'ref' => $ref,
                'title_en' => $titleEn,
                'title_ar' => $get('title_ar') !== '' ? mb_substr($get('title_ar'), 0, 200) : null,
                'description_en' => $get('description_en') !== '' ? $get('description_en') : null,
                'description_ar' => $get('description_ar') !== '' ? $get('description_ar') : null,
                'offering' => $offering,
                'property_type' => $type,
                'price' => $price,
                'currency' => $currency,
                'bedrooms' => $ints['bedrooms'] ?? null,
                'bathrooms' => $ints['bathrooms'] ?? null,
                'area_sqft' => $sqft,
                'community' => $get('community') !== '' ? mb_substr($get('community'), 0, 120) : null,
                'city' => $get('city') !== '' ? mb_substr($get('city'), 0, 80) : null,
                'status' => $status,
                'featured' => $featured,
            ],
            'media' => $media,
            'errors' => $errors,
        ];
    }
}
