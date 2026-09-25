<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Platform\Hosts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The agent's home on app.{base}: status, address and the way back into onboarding. The full
 * dashboard (spec §14) is Phase 4; this is what "Finish later" lands on.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $tenant = Tenant::query()->where('account_id', $request->user()?->getAttribute('account_id'))->orderBy('id')->first();
        if ($tenant === null) {
            return redirect()->route('start');
        }

        return view('app.home', [
            'tenant' => $tenant,
            'url' => Hosts::browserUrl($tenant->primaryHost(), '/'),
            'previewUrl' => Hosts::browserUrl($tenant->primaryHost(), '/?preview='.$tenant->previewToken()),
            'checklist' => SuccessController::checklist($tenant),
        ]);
    }
}
