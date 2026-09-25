<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\Exceptions\OtpThrottled;
use App\Auth\OtpService;
use App\Auth\Turnstile;
use App\Http\Controllers\Controller;
use App\Mail\SignInCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** "Send code": a 6-digit code and a magic link in one email (spec §13 S1). */
final class EmailCodeController extends Controller
{
    public function send(Request $request, OtpService $otp, Turnstile $turnstile): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:4000'],
        ]);
        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['email' => __('platform.signin.captcha_failed')])->withInput();
        }

        $email = strtolower(trim((string) $data['email']));
        try {
            ['otp' => $row, 'code' => $code] = $otp->issue($email, 'email', $request->ip());
        } catch (OtpThrottled $e) {
            return back()->withErrors(['email' => __('platform.signin.too_many', ['minutes' => max(1, (int) ceil($e->retryAfterSeconds / 60))])])->withInput();
        }

        $magic = URL::temporarySignedRoute('auth.magic', now()->addMinutes(OtpService::TTL_MINUTES), ['otp' => $row->id]);
        Mail::to($email)->locale(app()->getLocale())->send(new SignInCode($code, $magic));

        $request->session()->put('signin', ['channel' => 'email', 'identifier' => $email]);

        return redirect()->route('code');
    }
}
