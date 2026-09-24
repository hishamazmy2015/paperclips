<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Spec §15: platform:health, used by deploy.sh and the domain-change smoke test.
 * (A class rather than a Pest closure so PHPStan sees $this as the TestCase.)
 */
class PlatformHealthCommandTest extends TestCase
{
    public function test_reports_healthy_with_the_test_database_and_no_redis_backed_drivers(): void
    {
        $exit = Artisan::call('platform:health', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertTrue($report['ok']);
        $this->assertSame('example.test', $report['base_domain']);
        $this->assertSame('app.example.test', $report['hosts']['app']);
        $this->assertSame([], $report['legacy_hosts']);
        $this->assertTrue($report['checks']['database']['ok']);
        $this->assertTrue($report['checks']['redis']['ok']);
        $this->assertStringContainsString('skipped', $report['checks']['redis']['detail']);
        $this->assertTrue($report['checks']['media']['ok']);
    }

    public function test_fails_when_the_base_domain_is_not_configured(): void
    {
        config(['platform.base_domain' => '']);

        $this->artisan('platform:health')
            ->expectsOutputToContain('PLATFORM_BASE_DOMAIN is not set')
            ->assertExitCode(1);
    }

    public function test_fails_when_the_media_root_is_missing(): void
    {
        config(['platform.media_root' => '/nonexistent/media']);

        $this->artisan('platform:health')
            ->expectsOutputToContain('missing: /nonexistent/media')
            ->assertExitCode(1);
    }
}
