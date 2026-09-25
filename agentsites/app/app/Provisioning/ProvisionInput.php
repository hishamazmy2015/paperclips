<?php

declare(strict_types=1);

namespace App\Provisioning;

/**
 * Everything needed to create a site (spec §12). Built from the CLI, a JSON file
 * (samples/agent.json), a CSV row or the onboarding wizard. Scalars here win over the same
 * keys inside $config; $config carries any other schema field.
 */
final class ProvisionInput
{
    /**
     * @param  list<string>  $areas
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly int $accountId,
        public readonly string $name,
        public readonly string $whatsapp,
        public readonly ?string $slug = null,
        public readonly ?string $themeKey = null,
        public readonly ?string $locale = null,
        public readonly ?string $agency = null,
        public readonly ?string $license = null,
        public readonly ?string $brn = null,
        public readonly ?string $email = null,
        public readonly array $areas = [],
        public readonly array $config = [],
        public readonly bool $publish = false,
        /** An onboarding draft (spec §13 S1): name and WhatsApp may still be missing; AI content waits for publish. */
        public readonly bool $partial = false,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data, int $accountId, bool $publish = false): self
    {
        /** @var array<string, mixed> $config */
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $identity = is_array($config['identity'] ?? null) ? $config['identity'] : [];
        $contact = is_array($config['contact'] ?? null) ? $config['contact'] : [];
        $content = is_array($config['content'] ?? null) ? $config['content'] : [];
        $locale = is_array($config['locale'] ?? null) ? $config['locale'] : [];

        $areas = $data['areas'] ?? $data['service_areas'] ?? $content['service_areas'] ?? [];
        if (is_string($areas)) {
            $areas = preg_split('/[;,]/', $areas) ?: [];
        }

        return new self(
            accountId: $accountId,
            name: (string) ($data['name'] ?? $data['display_name'] ?? $identity['display_name'] ?? ''),
            whatsapp: (string) ($data['whatsapp'] ?? $contact['whatsapp'] ?? ''),
            slug: self::stringOrNull($data['slug'] ?? null),
            themeKey: self::stringOrNull($data['theme'] ?? $data['theme_key'] ?? null),
            locale: self::stringOrNull($data['locale'] ?? $locale['default'] ?? null),
            agency: self::stringOrNull($data['agency'] ?? $data['agency_name'] ?? $identity['agency_name'] ?? null),
            license: self::stringOrNull($data['license'] ?? $data['license_no'] ?? $identity['license_no'] ?? null),
            brn: self::stringOrNull($data['brn'] ?? $identity['brn'] ?? null),
            email: self::stringOrNull($data['email'] ?? $contact['email'] ?? null),
            areas: array_values(array_filter(array_map(fn (mixed $a): string => trim((string) $a), (array) $areas))),
            config: $config,
            publish: $publish || (bool) ($data['publish'] ?? false),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
