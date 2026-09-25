<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Provisioning\AgentCsv;
use App\Provisioning\AgentGenerator;
use App\Provisioning\ImportAgents;

/**
 * platform:site:generate --count=1000 [--seed=1] [--out=f.csv] [--provision [--draft] [--batch=100]]
 * Synthetic agents with distinct configs (goal G1 scale proof). With --provision the CSV is
 * imported straight away, so one command turns a number into N live sites.
 */
final class SiteGenerateCommand extends PlatformCommand
{
    protected $signature = 'platform:site:generate
        {--count=1000 : How many agents}
        {--seed=1 : Random seed (same seed, same CSV)}
        {--out= : Where to write the CSV (default storage/app/generated-agents-<seed>-<count>.csv)}
        {--provision : Import the generated CSV immediately}
        {--draft : With --provision: import as drafts}
        {--batch=100 : With --provision: rows per batch}';

    protected $description = 'Generate N synthetic agents as an import CSV (and optionally provision them)';

    public function handle(AgentGenerator $generator, ImportAgents $importer): int
    {
        $count = max(1, (int) $this->option('count'));
        $seed = (int) $this->option('seed');
        $out = (string) $this->option('out');
        if ($out === '') {
            $out = storage_path("app/generated-agents-{$seed}-{$count}.csv");
        }

        $written = AgentCsv::write($out, $generator->generate($count, $seed));
        $result = ['ok' => true, 'file' => $out, 'rows' => $written, 'seed' => $seed];

        if ((bool) $this->option('provision')) {
            $run = $importer->start($out, max(1, (int) $this->option('batch')), ! (bool) $this->option('draft'));
            $result['import'] = $run->summary();
        }

        return $this->emit($result, isset($result['import'])
            ? sprintf('Generated %d agents → %s; import #%d %s (%d created, %d errors, %ss)', $written, $out, $result['import']['id'], $result['import']['status'], $result['import']['created_rows'], $result['import']['error_rows'], $result['import']['seconds'] ?? '?')
            : sprintf('Generated %d agents → %s', $written, $out));
    }
}
