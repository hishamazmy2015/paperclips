<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Models\Tenant;
use App\Provisioning\Exceptions\InvalidProvisionInput;

/**
 * Clone(tenant, newSlug, overrides) — spec §12: the same provisioning path with the source
 * config minus identity/contact, plus the overrides (which must carry the new identity).
 */
final class CloneTenant
{
    public function __construct(private readonly ProvisionTenant $provisioner) {}

    /**
     * @param  array<string, mixed>  $overrides  ProvisionInput::fromArray shape (name, whatsapp, ...)
     */
    public function handle(Tenant $source, string $newSlug, array $overrides, ?int $accountId = null, bool $publish = false): Tenant
    {
        /** @var array<string, mixed> $config */
        $config = $source->config ?? [];
        unset($config['identity'], $config['contact'], $config['_schema']);
        unset($config['seo']['noindex']);
        // Generated copy names the source agent; it is regenerated for the new identity.
        foreach (array_keys($source->ai_generated_fields ?? []) as $generated) {
            data_forget($config, (string) $generated);
        }

        $overrideConfig = is_array($overrides['config'] ?? null) ? $overrides['config'] : [];
        $overrides['config'] = TenantConfig::mergeDefaults($config, $overrideConfig);
        $overrides['slug'] = $newSlug;
        $overrides['theme'] ??= $source->theme_key;

        $input = ProvisionInput::fromArray($overrides, $accountId ?? $source->account_id, $publish);
        if ($input->name === '' || $input->whatsapp === '') {
            throw new InvalidProvisionInput(['clone overrides must include name and whatsapp for the new site']);
        }

        return $this->provisioner->handle($input);
    }
}
