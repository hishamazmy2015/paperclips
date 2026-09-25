<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\Turnstile;
use App\Http\Controllers\Controller;
use App\Platform\EventLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** S1 sign in (spec §13): Google | email → code + magic link | optional phone → WhatsApp OTP. */
final class StartController extends Controller
{
    public function __invoke(Request $request, Turnstile $turnstile, EventLog $events): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('onboarding');
        }
        if ($request->session()->get('onboarding.started') !== true) {
            $events->record('onboarding.started');
            $request->session()->put('onboarding.started', true);
        }

        return view('app.start', [
            'turnstile' => $turnstile,
            'googleEnabled' => (string) config('services.google.client_id', '') !== '',
        ]);
    }
}
