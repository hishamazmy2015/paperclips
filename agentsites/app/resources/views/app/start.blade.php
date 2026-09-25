<x-layouts.app :title="__('platform.signin.title')">
    <section class="app-card" x-data="{ phone: false }">
        <h1 class="app-h1">{{ __('platform.signin.title') }}</h1>
        <p class="app-lead">{{ __('platform.signin.lead') }}</p>

        @if ($googleEnabled)
            <a href="{{ route('auth.google') }}" class="btn-google" data-test="google">
                <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.9 2.5 30.4 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.4 0 11.9-2.1 15.8-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.2 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                {{ __('platform.signin.google') }}
            </a>
            <div class="app-divider"><span>{{ __('platform.signin.or') }}</span></div>
        @endif

        <form method="post" action="{{ route('auth.email') }}" class="app-form" x-show="!phone">
            @csrf
            <label class="app-label" for="email">{{ __('platform.signin.email') }}</label>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required autofocus class="app-input" value="{{ old('email') }}" placeholder="name@example.com" data-test="email">
            @error('email') <p class="app-error" role="alert">{{ $message }}</p> @enderror
            @if ($turnstile->enabled())
                <div class="cf-turnstile" data-sitekey="{{ $turnstile->siteKey() }}" data-language="{{ app()->getLocale() }}"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @endif
            <button type="submit" class="btn-primary" data-test="send-code">{{ __('platform.signin.send_code') }}</button>
        </form>

        <form method="post" action="{{ route('auth.phone') }}" class="app-form" x-show="phone" x-cloak>
            @csrf
            <label class="app-label" for="phone">{{ __('platform.signin.phone_label') }}</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" class="app-input" dir="ltr" value="{{ old('phone', '+971') }}" data-test="phone">
            @error('phone') <p class="app-error" role="alert">{{ $message }}</p> @enderror
            @if ($turnstile->enabled())
                <div class="cf-turnstile" data-sitekey="{{ $turnstile->siteKey() }}" data-language="{{ app()->getLocale() }}"></div>
            @endif
            <button type="submit" class="btn-primary">{{ __('platform.signin.send_whatsapp') }}</button>
        </form>

        <button type="button" class="app-link" x-on:click="phone = !phone" data-test="toggle-phone">
            <span x-show="!phone">{{ __('platform.signin.phone') }}</span>
            <span x-show="phone" x-cloak>{{ __('platform.signin.use_email') }}</span>
        </button>
        <p class="app-fineprint">{{ __('platform.signin.terms') }}</p>
    </section>
</x-layouts.app>
