<?php

declare(strict_types=1);

namespace App\Provisioning;

use RuntimeException;

/**
 * The agents CSV contract (samples/agents.csv): one row per site, in the shape
 * ProvisionInput::fromArray understands. Extra columns become config fields.
 */
final class AgentCsv
{
    /** @var list<string> */
    public const COLUMNS = ['name', 'whatsapp', 'agency', 'license', 'slug', 'theme', 'areas', 'email', 'locale', 'palette', 'years', 'languages', 'instagram', 'dark_mode', 'photo', 'tagline_en', 'tagline_ar', 'bio_en', 'bio_ar'];

    /** @var list<string> */
    public const REQUIRED = ['name', 'whatsapp'];

    /**
     * Header of a CSV file (lower-cased, trimmed).
     *
     * @return list<string>
     */
    public static function header(string $path): array
    {
        $handle = self::open($path);
        $header = fgetcsv($handle, escape: '');
        fclose($handle);
        if (! is_array($header)) {
            throw new RuntimeException("empty CSV: {$path}");
        }
        $header = array_map(fn (mixed $h): string => strtolower(trim((string) $h)), $header);
        // strip a UTF-8 BOM from the first column
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        foreach (self::REQUIRED as $required) {
            if (! in_array($required, $header, true)) {
                throw new RuntimeException("CSV is missing the required column '{$required}': {$path}");
            }
        }

        return $header;
    }

    /** Number of data rows (blank lines ignored). */
    public static function count(string $path): int
    {
        $handle = self::open($path);
        fgetcsv($handle, escape: '');
        $n = 0;
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (! self::isBlank($row)) {
                $n++;
            }
        }
        fclose($handle);

        return $n;
    }

    /**
     * Data rows $from..$to (1-based, inclusive) as [rowNumber => assoc row].
     *
     * @return array<int, array<string, string>>
     */
    public static function rows(string $path, int $from, int $to): array
    {
        $header = self::header($path);
        $handle = self::open($path);
        fgetcsv($handle, escape: '');
        $rows = [];
        $n = 0;
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (self::isBlank($row)) {
                continue;
            }
            $n++;
            if ($n < $from) {
                continue;
            }
            if ($n > $to) {
                break;
            }
            $assoc = [];
            foreach ($header as $i => $column) {
                $assoc[$column] = trim((string) ($row[$i] ?? ''));
            }
            $rows[$n] = $assoc;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * A CSV row → the ProvisionInput::fromArray shape.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    public static function toData(array $row): array
    {
        $data = [];
        foreach (['name', 'whatsapp', 'agency', 'license', 'slug', 'theme', 'areas', 'email', 'locale'] as $key) {
            if (($row[$key] ?? '') !== '') {
                $data[$key] = $row[$key];
            }
        }

        $config = [];
        if (($row['palette'] ?? '') !== '') {
            $config['branding']['palette'] = strtolower($row['palette']);
        }
        if (($row['dark_mode'] ?? '') !== '') {
            $config['branding']['dark_mode'] = strtolower($row['dark_mode']);
        }
        if (($row['years'] ?? '') !== '' && is_numeric($row['years'])) {
            $config['identity']['years_experience'] = (int) $row['years'];
        }
        if (($row['languages'] ?? '') !== '') {
            $config['identity']['languages'] = array_values(array_filter(array_map('trim', preg_split('/[;,]/', strtolower($row['languages'])) ?: [])));
        }
        if (($row['photo'] ?? '') !== '') {
            $config['identity']['photo'] = $row['photo'];
        }
        if (($row['instagram'] ?? '') !== '') {
            $handle = ltrim($row['instagram'], '@');
            $config['contact']['socials']['instagram'] = str_starts_with($handle, 'http') ? $handle : 'https://instagram.com/'.$handle;
        }
        foreach (['tagline', 'bio'] as $field) {
            foreach (['en', 'ar'] as $locale) {
                if (($row[$field.'_'.$locale] ?? '') !== '') {
                    $config['identity'][$field][$locale] = $row[$field.'_'.$locale];
                }
            }
        }
        if ($config !== []) {
            $data['config'] = $config;
        }

        return $data;
    }

    /**
     * Write rows to a CSV file in the standard column order.
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    public static function write(string $path, iterable $rows): int
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException("cannot write {$path}");
        }
        fputcsv($handle, self::COLUMNS, escape: '');
        $n = 0;
        foreach ($rows as $row) {
            $line = [];
            foreach (self::COLUMNS as $column) {
                $value = $row[$column] ?? '';
                $line[] = is_array($value) ? implode(';', $value) : (string) $value;
            }
            fputcsv($handle, $line, escape: '');
            $n++;
        }
        fclose($handle);

        return $n;
    }

    /**
     * fgetcsv() yields a single null cell for a blank line.
     *
     * @param  array<int, string|null>  $row
     */
    private static function isBlank(array $row): bool
    {
        return count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === '');
    }

    /** @return resource */
    private static function open(string $path)
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("cannot read CSV: {$path}");
        }

        return $handle;
    }
}
