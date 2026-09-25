<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\OtpService;
use App\Auth\SignIn;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The magic link from the sign-in email: signed, 10 minutes, single use (spec §13 S1). */
final class MagicLinkController extends Controller
{
    public function __invoke(Request $request, OtpService $otp, SignIn $signIn, string $otpId): RedirectResponse
    {
        $row = ctype_digit($otpId) ? $otp->consume((int) $otpId) : null;
        if ($row === null || $row->channel !== 'email') {
            return redirect()->route('start')->withErrors(['email' => __('platform.signin.link_expired')]);
        }

        $signIn->withEmail($row->identifier, app()->getLocale());
        $request->session()->forget('signin');

        return redirect()->route('onboarding');
    }
}
