<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Platform\Hosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * platform:health — the first of the platform:* commands (spec §15). Used by deploy.sh,
 * the domain-change smoke test and operators. Exit code 0 only when every check passes.
 */
class PlatformHealthCommand extends Command
{
    protected $signature = 'platform:health {--json : Machine-readable output}';

    protected $description = 'Report platform health: base domain, derived hosts, database, redis, media root';

    public function handle(): int
    {
        $checks = [
            'base_domain' => $this->checkBaseDomain(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'media' => $this->checkMedia(),
        ];
        $ok = ! in_array(false, array_column($checks, 'ok'), true);

        $report = [
            'ok' => $ok,
            'app_env' => (string) config('app.env'),
            'base_domain' => Hosts::base(),
            'hosts' => config('platform.hosts'),
            'legacy_hosts' => config('platform.legacy_hosts'),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Platform health: %s (env %s, base domain %s)', $ok ? 'OK' : 'DEGRADED', $report['app_env'], Hosts::base()));
            foreach ($checks as $name => $check) {
                $this->line(sprintf('  [%s] %-12s %s', $check['ok'] ? 'ok' : '!!', $name, $check['detail']));
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ok: bool, detail: string} */
    private function checkBaseDomain(): array
    {
        $base = Hosts::base();
        if ($base === '' || $base === 'example.com') {
            return ['ok' => false, 'detail' => 'PLATFORM_BASE_DOMAIN is not set in .env'];
        }

        return ['ok' => true, 'detail' => $base.' (app: '.Hosts::app().', admin: '.Hosts::admin().', api: '.Hosts::api().')'];
    }

    /** @return array{ok: bool, detail: string} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => (string) config('database.default')];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkRedis(): array
    {
        $drivers = [config('cache.default'), config('queue.default'), config('session.driver')];
        if (! in_array('redis', $drivers, true)) {
            return ['ok' => true, 'detail' => 'no redis-backed driver configured (skipped)'];
        }

        try {
            Redis::connection()->ping();

            return ['ok' => true, 'detail' => 'pong'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function checkMedia(): array
    {
        $root = (string) config('platform.media_root');
        if (! is_dir($root)) {
            return ['ok' => false, 'detail' => 'missing: '.$root];
        }

        return is_writable($root)
            ? ['ok' => true, 'detail' => $root]
            : ['ok' => false, 'detail' => 'not writable: '.$root];
    }
}
