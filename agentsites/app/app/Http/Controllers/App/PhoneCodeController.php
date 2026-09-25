<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\Exceptions\OtpThrottled;
use App\Auth\OtpService;
use App\Auth\Turnstile;
use App\Http\Controllers\Controller;
use App\Messaging\Notifier;
use App\Provisioning\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Optional phone sign-in: the code arrives on WhatsApp (spec §13 S1, §6). */
final class PhoneCodeController extends Controller
{
    public function send(Request $request, OtpService $otp, Turnstile $turnstile, PhoneNormalizer $phones, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:4000'],
        ]);
        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['phone' => __('platform.signin.captcha_failed')])->withInput();
        }

        $phone = $phones->normalize((string) $data['phone']);
        if ($phone === null) {
            return back()->withErrors(['phone' => __('platform.signin.phone_invalid')])->withInput();
        }

        try {
            ['code' => $code] = $otp->issue($phone, 'phone', $request->ip());
        } catch (OtpThrottled $e) {
            return back()->withErrors(['phone' => __('platform.signin.too_many', ['minutes' => max(1, (int) ceil($e->retryAfterSeconds / 60))])])->withInput();
        }

        $result = $notifier->whatsapp($phone, __('platform.signin.whatsapp_code', ['code' => $code, 'minutes' => OtpService::TTL_MINUTES]));
        if (! $result->accepted) {
            return back()->withErrors(['phone' => __('platform.signin.whatsapp_failed')])->withInput();
        }

        $request->session()->put('signin', ['channel' => 'phone', 'identifier' => $phone]);

        return redirect()->route('code');
    }
}
