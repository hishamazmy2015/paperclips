<div class="app-card" data-test="feeds">
    <x-app-nav active="feeds" />
    <h1 class="app-h1">{{ __('platform.feeds.title') }}</h1>
    <p class="app-lead">{{ __('platform.feeds.lead') }}</p>

    @if ($feeds->isEmpty())
        <p class="app-fineprint" data-test="no-feeds">{{ __('platform.feeds.none') }}</p>
    @else
        <ul class="listing-rows" data-test="feed-rows">
            @foreach ($feeds as $feed)
                <li class="listing-row {{ $feed->active ? '' : 'is-hidden' }}" wire:key="feed-{{ $feed->id }}" data-test="feed-{{ $feed->id }}">
                    <span class="listing-row-text">
                        <strong>{{ __('platform.feeds.provider_'.$feed->provider) }}</strong>
                        <small dir="ltr">{{ \Illuminate\Support\Str::limit((string) data_get($feed->credentials, 'url'), 60) }}</small>
                        <small>{{ __('platform.feeds.listings_count', ['count' => $counts[$feed->id] ?? 0]) }} · {{ $feed->last_sync_at ? __('platform.feeds.last_sync', ['when' => $feed->last_sync_at->diffForHumans()]) : __('platform.feeds.never_synced') }}@if ($feed->last_error) · <span class="text-bad">{{ __('platform.feeds.error') }}: {{ \Illuminate\Support\Str::limit($feed->last_error, 120) }}</span>@endif</small>
                    </span>
                    <span class="listing-row-actions">
                        <button type="button" class="app-chip" wire:click="syncNow({{ $feed->id }})" wire:loading.attr="disabled" data-test="sync-{{ $feed->id }}">{{ __('platform.feeds.sync_now') }}</button>
                        <button type="button" class="app-chip" wire:click="toggle({{ $feed->id }})" data-test="toggle-{{ $feed->id }}">{{ $feed->active ? __('platform.feeds.pause') : __('platform.feeds.resume') }}</button>
                        <button type="button" class="app-chip" wire:click="remove({{ $feed->id }})" wire:confirm="{{ __('platform.feeds.remove_confirm') }}" data-test="remove-{{ $feed->id }}">{{ __('platform.listings.delete') }}</button>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($lastResult !== null)
        <p class="app-ok-text" role="status" data-test="sync-result">{{ __('platform.feeds.sync_result', ['fetched' => $lastResult['fetched'], 'created' => $lastResult['created'], 'updated' => $lastResult['updated'], 'hidden' => $lastResult['hidden'], 'errors' => count($lastResult['errors'])]) }}</p>
    @endif

    <h2 class="app-h2">{{ __('platform.feeds.add') }}</h2>
    @if ($error !== '')<p class="app-error" role="alert" data-test="feed-error">{{ $error }}</p>@endif
    <form wire:submit="add" class="wizard-fields">
        <label class="app-field"><span class="app-label">{{ __('platform.feeds.provider') }}</span>
            <select class="app-input" wire:model="provider" data-test="provider">@foreach ($providers as $p)<option value="{{ $p }}">{{ __('platform.feeds.provider_'.$p) }}</option>@endforeach</select></label>
        <label class="app-field"><span class="app-label">{{ __('platform.feeds.url') }}</span><input type="url" class="app-input" dir="ltr" wire:model="url" placeholder="https://…/listings.xml" required data-test="feed-url"></label>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.feeds.auth_header') }}</span><input type="text" class="app-input" dir="ltr" wire:model="authHeader" placeholder="X-Api-Key"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.feeds.auth_value') }}</span><input type="password" class="app-input" dir="ltr" wire:model="authValue" autocomplete="off"></label>
        </div>
        <p class="app-label">{{ __('platform.feeds.filters') }}</p>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.offering') }}</span>
                <select class="app-input" wire:model="offering"><option value="">{{ __('site.listing.any') }}</option><option value="sale">{{ __('site.listing.for_sale') }}</option><option value="rent">{{ __('site.listing.for_rent') }}</option></select></label>
            <label class="app-field"><span class="app-label">{{ __('platform.feeds.communities') }}</span><input type="text" class="app-input" wire:model="communities" placeholder="Downtown, Marina"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.feeds.min_price') }}</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="minPrice"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.feeds.max_price') }}</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="maxPrice"></label>
        </div>
        <label class="app-field"><span class="app-label">{{ __('platform.feeds.property_types') }}</span><input type="text" class="app-input" dir="ltr" wire:model="propertyTypes" placeholder="apartment, villa"></label>
        <button type="submit" class="btn-primary" data-test="add-feed">{{ __('platform.feeds.connect') }}</button>
    </form>
    <p class="app-fineprint">{{ __('platform.feeds.format_hint') }}</p>
</div>
