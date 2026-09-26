<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/** The signed-in agent's own site (one per account until brokerages arrive in Phase 6). */
trait OwnsTenant
{
    private ?Tenant $ownTenant = null;

    protected function tenant(): Tenant
    {
        if ($this->ownTenant === null) {
            $tenant = Tenant::query()->where('account_id', Auth::user()?->getAttribute('account_id'))->orderBy('id')->first();
            if ($tenant === null) {
                abort(404);
            }
            $this->ownTenant = $tenant;
        }

        return $this->ownTenant;
    }
}
