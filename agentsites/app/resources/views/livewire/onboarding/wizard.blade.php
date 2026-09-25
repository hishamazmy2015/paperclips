<div class="wizard" data-step="{{ $step }}" data-test="wizard">
    <x-slot:progress>{{ $step }}</x-slot:progress>

    <h1 class="app-h1">{{ $titles[$step] }}</h1>

    {{-- ── S2 About you (1/3) ─────────────────────────────────────────── --}}
    @if ($step === 1)
        <div class="wizard-fields">
            <label class="app-label" for="name">{{ __('platform.wizard.name') }}</label>
            <input id="name" type="text" class="app-input" wire:model.live.debounce.400ms="name" autocomplete="name" required maxlength="80" data-test="name">

            <label class="app-label" for="whatsapp">{{ __('platform.wizard.whatsapp') }}</label>
            <div class="app-input-row">
                <input id="whatsapp" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" class="app-input {{ $whatsappState === 'invalid' ? 'is-invalid' : '' }}" placeholder="+971 50 123 4567" wire:model.live.debounce.400ms="whatsapp" data-test="whatsapp">
                @if ($whatsappState === 'ok') <span class="app-ok" data-test="whatsapp-ok">✓</span> @endif
            </div>
            @if ($whatsappState === 'invalid') <p class="app-error" role="alert">{{ __('platform.wizard.whatsapp_invalid') }}</p> @endif

            <label class="app-label" for="agency">{{ __('platform.wizard.agency') }}</label>
            <input id="agency" type="text" class="app-input" list="brokerages" autocomplete="organization" maxlength="120" wire:model.live.debounce.500ms="agency" data-test="agency">
            <datalist id="brokerages">
                @foreach ($brokerages as $brokerage) <option value="{{ $brokerage }}"></option> @endforeach
            </datalist>

            <label class="app-label" for="license">{{ __('platform.wizard.license') }}</label>
            <input id="license" type="text" class="app-input" maxlength="40" wire:model.live.debounce.500ms="license" data-test="license">

            <div class="wizard-photo">
                <label class="app-photo" for="photo" data-test="photo-label">
                    @if ($photoUrl !== '')
                        <img src="{{ $photoUrl }}" alt="" class="app-photo-img" width="56" height="56">
                    @else
                        <span class="app-photo-empty" aria-hidden="true">{{ mb_substr(trim($name) !== '' ? $name : '?', 0, 1) }}</span>
                    @endif
                    <span>{{ __('platform.wizard.photo') }}</span>
                    <input id="photo" type="file" accept="image/*" capture="user" class="sr-only" wire:model="photo">
                </label>
                <span class="app-fineprint" wire:loading wire:target="photo">{{ __('platform.wizard.uploading') }}</span>
                @if ($photoError !== '') <p class="app-error" role="alert">{{ $photoError }}</p> @endif
            </div>
        </div>
    @endif

    {{-- ── S3 Your website (2/3) ──────────────────────────────────────── --}}
    @if ($step === 2)
        <div class="wizard-fields">
            <label class="app-label" for="slug">{{ __('platform.wizard.subdomain') }}</label>
            <div class="app-slug" dir="ltr">
                <input id="slug" type="text" class="app-input app-slug-input {{ $slugState !== '' && $slugState !== 'available' ? 'is-invalid' : '' }}" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="40" pattern="[a-z0-9-]*" wire:model.live.debounce.300ms="slug" data-test="slug">
                <span class="app-slug-suffix">.{{ $base }}</span>
            </div>
            <p class="app-slug-url" dir="ltr" data-test="slug-url">https://{{ $slug }}.{{ $base }}</p>
            @if ($slugState === 'available')
                <p class="app-ok-text" data-test="slug-available">{{ __('platform.wizard.subdomain_available') }}</p>
            @elseif ($slugState !== '')
                <p class="app-error" role="alert" data-test="slug-taken">{{ __('platform.wizard.subdomain_taken') }}</p>
                <div class="app-chips">
                    @foreach ($suggestions as $suggestion)
                        <button type="button" class="app-chip" dir="ltr" wire:click="useSuggestion('{{ $suggestion }}')">{{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif

            <p class="app-label">{{ __('platform.wizard.theme') }}</p>
            <div class="theme-cards" data-test="theme-cards">
                @foreach ($themes as $key => $info)
                    <button type="button" class="theme-card {{ $theme === $key ? 'is-selected' : '' }} {{ $info['installed'] ? '' : 'is-disabled' }}" wire:click="selectTheme('{{ $key }}')" @disabled(! $info['installed']) aria-pressed="{{ $theme === $key ? 'true' : 'false' }}" data-test="theme-{{ $key }}">
                        <span class="theme-mock theme-mock-{{ $key }}" style="@foreach ($paletteVars as $var => $value){{ $var }}:{{ $value }};@endforeach">
                            <span class="theme-mock-bar"></span>
                            <span class="theme-mock-hero">
                                @if ($photoUrl !== '') <img src="{{ $photoUrl }}" alt="" width="24" height="24"> @else <i></i> @endif
                                <b>{{ trim($name) !== '' ? $name : $tenant->displayName() }}</b>
                                <small>{{ $agency !== '' ? $agency : __('platform.wizard.your_website') }}</small>
                                <em></em>
                            </span>
                            <span class="theme-mock-grid"><span></span><span></span><span></span></span>
                        </span>
                        <span class="theme-card-name">{{ $info['name'] }}@if (! $info['installed']) <small>{{ __('platform.wizard.coming_soon') }}</small>@endif</span>
                    </button>
                @endforeach
            </div>

            <p class="app-label">{{ __('platform.wizard.palette') }}</p>
            <div class="palette-row" data-test="palettes">
                @foreach ($palettes as $key => $colors)
                    <button type="button" class="palette-swatch {{ $palette === $key ? 'is-selected' : '' }}" style="--sw:{{ $colors['primary'] }};--sw2:{{ $colors['accent'] }}" wire:click="selectPalette('{{ $key }}')" aria-label="{{ $key }}" aria-pressed="{{ $palette === $key ? 'true' : 'false' }}" data-test="palette-{{ $key }}"></button>
                @endforeach
                <label class="palette-logo {{ $palette === 'custom' ? 'is-selected' : '' }}" style="--sw:{{ $customPalette['primary'] ?? '#888888' }};--sw2:{{ $customPalette['accent'] ?? '#bbbbbb' }}" data-test="from-logo">
                    <span>{{ __('platform.wizard.from_logo') }}</span>
                    <input type="file" accept="image/*" class="sr-only" wire:model="logo">
                </label>
            </div>
            <span class="app-fineprint" wire:loading wire:target="logo">{{ __('platform.wizard.uploading') }}</span>
            @if ($logoError !== '') <p class="app-error" role="alert">{{ $logoError }}</p> @endif
        </div>
    @endif

    {{-- ── S4 Go live (3/3) ────────────────────────────────────────────── --}}
    @if ($step === 3)
        <div class="wizard-fields wizard-golive">
            <p class="app-label">{{ __('platform.wizard.areas') }}</p>
            <div class="app-chips" data-test="areas">
                @foreach ($areaOptions as $area)
                    <button type="button" class="app-chip {{ in_array($area, $areas, true) ? 'is-selected' : '' }}" wire:click="toggleArea('{{ addslashes($area) }}')" aria-pressed="{{ in_array($area, $areas, true) ? 'true' : 'false' }}">{{ $area }}</button>
                @endforeach
            </div>

            <div class="preview-frame" data-test="preview">
                <iframe src="{{ $previewUrl }}" title="{{ __('platform.wizard.preview') }}" loading="eager" referrerpolicy="no-referrer" sandbox="allow-same-origin allow-scripts"></iframe>
            </div>
            @if ($publishError !== '') <p class="app-error" role="alert">{{ $publishError }}</p> @endif
        </div>
    @endif

    {{-- ── navigation ─────────────────────────────────────────────────── --}}
    <div class="wizard-nav">
        @if ($step > 1)
            <button type="button" class="btn-secondary" wire:click="back" data-test="back">{{ __('platform.wizard.back') }}</button>
        @endif
        @if ($step < 3)
            <button type="button" class="btn-primary" wire:click="next" @disabled(! $canContinue) data-test="next">{{ __('platform.wizard.next') }}</button>
        @else
            <button type="button" class="btn-primary" wire:click="publish" wire:loading.attr="disabled" data-test="publish">
                <span wire:loading.remove wire:target="publish">{{ __('platform.wizard.publish') }}</span>
                <span wire:loading wire:target="publish">{{ __('platform.wizard.publishing') }}</span>
            </button>
            <button type="button" class="app-link" wire:click="finishLater" data-test="finish-later">{{ __('platform.wizard.finish_later') }}</button>
        @endif
    </div>
    <p class="app-fineprint wizard-autosave" wire:loading.class.remove="is-idle" wire:target="name,whatsapp,agency,license,slug,photo,logo">{{ __('platform.wizard.autosaved') }}</p>
</div>
