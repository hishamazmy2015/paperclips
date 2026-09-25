<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Provisioning\CloneTenant;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Provisioning\Exceptions\SlugUnavailable;

/** platform:site:clone <slug> --to <slug> --name ... --whatsapp ... (spec §12, §15). */
final class SiteCloneCommand extends PlatformCommand
{
    protected $signature = 'platform:site:clone {slug} {--to= : New slug} {--name= : New agent name} {--whatsapp= : New WhatsApp number} {--email=} {--agency=} {--account= : Account id (default: same account)} {--draft}';

    protected $description = 'Clone a site: same config minus identity/contact, plus the overrides';

    public function handle(CloneTenant $cloner): int
    {
        $source = $this->findTenant((string) $this->argument('slug'));
        if ($source === null) {
            return $this->failWith("no tenant with slug '{$this->argument('slug')}'");
        }
        $to = (string) $this->option('to');
        if ($to === '') {
            return $this->failWith('--to is required');
        }

        $overrides = array_filter([
            'name' => $this->option('name'),
            'whatsapp' => $this->option('whatsapp'),
            'email' => $this->option('email'),
            'agency' => $this->option('agency'),
        ], fn (mixed $v): bool => is_string($v) && $v !== '');

        try {
            $accountId = is_string($this->option('account')) && $this->option('account') !== '' ? (int) $this->option('account') : null;
            $clone = $cloner->handle($source, $to, $overrides, $accountId, publish: ! (bool) $this->option('draft'));
        } catch (SlugUnavailable $e) {
            return $this->failWith($e->getMessage(), ['slug' => $e->slug, 'reason' => $e->reason, 'suggestions' => $e->suggestions]);
        } catch (InvalidProvisionInput $e) {
            return $this->failWith('invalid input', ['errors' => $e->errors]);
        }

        return $this->emit($this->describe($clone) + ['cloned_from' => $source->slug], sprintf('Cloned %s → %s (%s)', $source->slug, $clone->slug, $clone->url()));
    }
}
