<x-layouts.app :title="__('platform.signin.code')">
    <section class="app-card">
        <h1 class="app-h1">{{ __('platform.signin.code') }}</h1>
        <p class="app-lead">{{ __($channel === 'phone' ? 'platform.signin.sent_whatsapp' : 'platform.signin.sent_email', ['to' => $identifier]) }}</p>

        <form method="post" action="{{ route('code.verify') }}" class="app-form" x-data>
            @csrf
            <label class="app-label sr-only" for="code">{{ __('platform.signin.code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9٠-٩]*" maxlength="6" required autofocus
                   class="app-input app-code" dir="ltr" placeholder="••••••" data-test="code"
                   x-on:input="if ($el.value.replace(/\D/g, '').length === 6) $el.form.requestSubmit()">
            @error('code') <p class="app-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary">{{ __('platform.signin.continue') }}</button>
        </form>

        @if ($channel === 'email')
            <p class="app-fineprint">{{ __('platform.signin.magic_hint') }}</p>
        @endif
        <a href="{{ route('start') }}" class="app-link">{{ __('platform.signin.resend') }}</a>
    </section>
</x-layouts.app>
