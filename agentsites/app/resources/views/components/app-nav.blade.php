@props(['active' => ''])
<nav class="app-nav" aria-label="{{ __('platform.nav.label') }}">
    <a href="{{ route('home') }}" class="{{ $active === 'home' ? 'is-active' : '' }}">{{ __('platform.nav.home') }}</a>
    <a href="{{ route('listings') }}" class="{{ $active === 'listings' ? 'is-active' : '' }}" data-test="nav-listings">{{ __('platform.nav.listings') }}</a>
    <a href="{{ route('listings.import') }}" class="{{ $active === 'import' ? 'is-active' : '' }}" data-test="nav-import">{{ __('platform.nav.import') }}</a>
    <a href="{{ route('listings.feeds') }}" class="{{ $active === 'feeds' ? 'is-active' : '' }}" data-test="nav-feeds">{{ __('platform.nav.feeds') }}</a>
</nav>
