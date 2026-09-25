<x-layouts.app :title="__('platform.success.published')">
    <section class="app-card app-success" x-data="{ copied: false }" data-test="success">
        <canvas id="confetti" class="app-confetti" aria-hidden="true"></canvas>
        <h1 class="app-h1">{{ __('platform.success.published') }}</h1>

        <button type="button" class="app-url" dir="ltr" data-test="site-url"
                x-on:click="navigator.clipboard?.writeText(@js($url)).then(() => { copied = true; fetch(@js(route('share', 'copy')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }); })">
            <span class="app-url-text">{{ preg_replace('#^https?://#', '', rtrim($url, '/')) }}</span>
            <span class="app-url-hint" x-text="copied ? @js(__('platform.success.copied')) : @js(__('platform.success.copy'))"></span>
        </button>

        <div class="app-qr" data-test="qr">{!! $qr !!}</div>

        <div class="app-actions">
            <a href="{{ route('share', 'whatsapp') }}" class="btn-whatsapp" data-test="share-whatsapp">{{ __('platform.success.share_whatsapp') }}</a>
            <a href="{{ route('share', 'open') }}" class="btn-primary" target="_blank" rel="noopener" data-test="open-site">{{ __('platform.success.open_site') }}</a>
        </div>

        <h2 class="app-h2">{{ __('platform.success.checklist', ['percent' => $percent]) }}</h2>
        <ul class="app-checklist" data-test="checklist">
            @foreach ($checklist as $key => $done)
                <li class="{{ $done ? 'is-done' : '' }}">
                    <span class="app-check" aria-hidden="true">{{ $done ? '✓' : '' }}</span>
                    {{ __('platform.success.'.($key === 'domain' ? 'connect_domain' : 'add_'.$key)) }}
                </li>
            @endforeach
        </ul>
        <a href="{{ route('home') }}" class="app-link">{{ __('platform.home.title') }}</a>
    </section>
    <script>window.addEventListener('load', () => window.agentsitesConfetti && window.agentsitesConfetti(document.getElementById('confetti')));</script>
</x-layouts.app>
