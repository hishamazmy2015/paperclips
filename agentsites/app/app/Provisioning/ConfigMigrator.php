<?php

declare(strict_types=1);

namespace App\Provisioning;

/**
 * Upgrades a stored tenant config from an older schema version to the current one, lazily
 * at read time (spec §9). Each step is a pure function of the config; steps run in order.
 */
final class ConfigMigrator
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function upgrade(array $config): array
    {
        $version = (int) ($config['_schema'] ?? 1);

        while ($version < TenantConfig::SCHEMA_VERSION) {
            $config = $this->step($version, $config);
            $version++;
            $config['_schema'] = $version;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function step(int $fromVersion, array $config): array
    {
        // No migrations yet: version 1 is the first schema. Add `case 1:` (1 → 2) here.
        return match ($fromVersion) {
            default => $config,
        };
    }
}
