<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\Exceptions\OtpRejected;
use App\Auth\Exceptions\OtpThrottled;
use App\Auth\OtpService;
use App\Auth\SignIn;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The 6-digit input (auto-submit) for the code sent by email or WhatsApp (spec §13 S1). */
final class CodeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('signin');
        if (! is_array($pending)) {
            return redirect()->route('start');
        }

        return view('app.code', ['channel' => (string) $pending['channel'], 'identifier' => (string) $pending['identifier']]);
    }

    public function verify(Request $request, OtpService $otp, SignIn $signIn): RedirectResponse
    {
        $pending = $request->session()->get('signin');
        if (! is_array($pending)) {
            return redirect()->route('start');
        }
        $identifier = (string) $pending['identifier'];
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        try {
            $otp->verify($identifier, (string) $data['code'], $request->ip());
        } catch (OtpRejected $e) {
            $message = match ($e->reason) {
                'expired' => __('platform.signin.code_expired'),
                'attempts' => __('platform.signin.code_attempts'),
                default => __('platform.signin.code_invalid', ['left' => $e->attemptsLeft ?? 0]),
            };

            return back()->withErrors(['code' => $message]);
        } catch (OtpThrottled $e) {
            return back()->withErrors(['code' => __('platform.signin.too_many', ['minutes' => max(1, (int) ceil($e->retryAfterSeconds / 60))])]);
        }

        $locale = app()->getLocale();
        $pending['channel'] === 'phone'
            ? $signIn->withPhone($identifier, $locale)
            : $signIn->withEmail($identifier, $locale);
        $request->session()->forget('signin');

        return redirect()->route('onboarding');
    }
}
