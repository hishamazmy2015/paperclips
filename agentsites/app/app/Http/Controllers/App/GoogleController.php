<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Auth\SignIn;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** "Continue with Google" (spec §6, §13 S1) through Socialite; the account is matched by email or Google id. */
final class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if ((string) config('services.google.client_id', '') === '') {
            return redirect()->route('start')->withErrors(['email' => __('platform.signin.google_unavailable')]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SignIn $signIn): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('start')->withErrors(['email' => __('platform.signin.google_failed')]);
        }

        $email = (string) $google->getEmail();
        if ($email === '') {
            return redirect()->route('start')->withErrors(['email' => __('platform.signin.google_failed')]);
        }

        $signIn->withEmail($email, app()->getLocale(), name: $google->getName(), provider: 'google', googleId: (string) $google->getId());
        $request->session()->forget('signin');

        return redirect()->route('onboarding');
    }
}
