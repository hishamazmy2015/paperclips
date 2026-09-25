<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Platform\EventLog;
use App\Platform\Hosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** share.clicked{channel} (spec §13 S5), then off to WhatsApp / the site; "copy" is a beacon. */
final class ShareController extends Controller
{
    public function __invoke(Request $request, EventLog $events, string $channel): RedirectResponse|Response
    {
        $tenant = Tenant::query()->where('account_id', $request->user()?->getAttribute('account_id'))->orderBy('id')->first();
        if ($tenant === null) {
            return redirect()->route('start');
        }
        $events->record('share.clicked', ['channel' => $channel], tenant: $tenant, account: $tenant->account, user: $request->user());

        $url = Hosts::browserUrl($tenant->primaryHost(), '/');

        return match ($channel) {
            'whatsapp' => redirect()->away('https://wa.me/?text='.rawurlencode(__('platform.success.share_text', ['url' => $url]))),
            'open' => redirect()->away($url),
            default => new Response('', 204),
        };
    }
}
