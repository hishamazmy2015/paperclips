<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Caching\PageCache;
use App\Models\Tenant;
use App\Provisioning\Exceptions\InvalidTenantConfig;
use App\Tenancy\HostCache;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * The tenant config contract (spec §9): JSON-Schema validation, read-time defaults, lazy
 * schema migration and the config_version revision counter.
 *
 * What is stored is only what the agent (or the generator) set. Defaults from
 * config/tenant-defaults.php are merged when read, so changing a default changes every site.
 */
final class TenantConfig
{
    /** Stored under config["_schema"]; ConfigMigrator upgrades older values lazily. */
    public const SCHEMA_VERSION = 1;

    private ?Validator $validator = null;

    private ?object $schema = null;

    public function __construct(
        private readonly ConfigMigrator $migrator,
        private readonly HostCache $hosts,
        private readonly PageCache $pages,
    ) {}

    public static function schemaPath(): string
    {
        return database_path('schemas/tenant-config.schema.json');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string> "path: message" per violation; empty when valid
     */
    public function validate(array $config): array
    {
        $data = self::toJsonValue($this->normalize($config));
        $result = $this->validator()->validate($data, $this->schema());
        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        $error = $result->error();
        if ($error !== null) {
            foreach ((new ErrorFormatter)->formatFlat($error) as $message) {
                $errors[] = $message;
            }
            foreach ((new ErrorFormatter)->formatKeyed($error) as $path => $messages) {
                foreach ($messages as $message) {
                    $errors[] = $path.': '.$message;
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Drop nulls, empty strings and empty arrays recursively; trim strings. The stored config
     * holds only what was actually set.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalize(array $config): array
    {
        $out = [];
        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if (is_array($value)) {
                $value = array_is_list($value)
                    ? array_values(array_filter(array_map(fn (mixed $v): mixed => is_array($v) ? $this->normalize($v) : (is_string($v) ? trim($v) : $v), $value), fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []))
                    : $this->normalize($value);
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Stored config + defaults (spec §9), after a lazy schema migration if needed.
     *
     * @return array<string, mixed>
     */
    public function merged(Tenant $tenant): array
    {
        /** @var array<string, mixed> $stored */
        $stored = $tenant->config ?? [];
        $stored = $this->migrator->upgrade($stored);
        /** @var array<string, mixed> $defaults */
        $defaults = config('tenant-defaults', []);

        $merged = self::mergeDefaults($defaults, $stored);
        unset($merged['_schema']);

        return $merged;
    }

    /**
     * Validate, normalise and persist a new stored config; config_version increments on
     * every save (spec §9).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidTenantConfig
     */
    public function save(Tenant $tenant, array $config): Tenant
    {
        $config = $this->normalize($config);
        $config['_schema'] = self::SCHEMA_VERSION;

        $errors = $this->validate($config);
        if ($errors !== []) {
            throw new InvalidTenantConfig($errors);
        }

        $tenant->config = $config;
        $tenant->config_version = (int) $tenant->config_version + 1;
        $tenant->save();

        // The edge caches the tenant with its config: a save must be visible on the next
        // request (site editor "live in ≤ 5 s", spec §14).
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);

        return $tenant;
    }

    /**
     * Defaults deep-merged with stored values: objects merge key by key, lists and scalars
     * from the stored config replace the default entirely.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function mergeDefaults(array $defaults, array $stored): array
    {
        $out = $defaults;
        foreach ($stored as $key => $value) {
            $default = $out[$key] ?? null;
            if (is_array($value) && is_array($default) && ! array_is_list($value) && ! array_is_list($default)) {
                $out[$key] = self::mergeDefaults($default, $value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** Assoc arrays become objects, lists stay lists — what a JSON-Schema validator expects. */
    public static function toJsonValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::toJsonValue(...), $value);
        }
        $object = new \stdClass;
        foreach ($value as $key => $item) {
            $object->{(string) $key} = self::toJsonValue($item);
        }

        return $object;
    }

    private function validator(): Validator
    {
        return $this->validator ??= new Validator;
    }

    private function schema(): object
    {
        if ($this->schema === null) {
            /** @var object $schema */
            $schema = json_decode((string) file_get_contents(self::schemaPath()), false, 512, JSON_THROW_ON_ERROR);
            $this->schema = $schema;
        }

        return $this->schema;
    }
}
