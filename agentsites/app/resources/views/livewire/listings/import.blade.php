<div class="app-card" data-test="listings-import">
    <x-app-nav active="import" />
    <h1 class="app-h1">{{ __('platform.listings.import') }}</h1>
    <p class="app-lead">{{ __('platform.listings.import_lead') }} <a href="{{ route('listings.template') }}" class="app-link" data-test="template">{{ __('platform.listings.template') }}</a></p>

    <label class="app-field">
        <span class="app-label">{{ __('platform.listings.csv_file') }}</span>
        <input type="file" accept=".csv,text/csv" class="app-input" wire:model="file" data-test="csv-input">
    </label>
    <span class="app-fineprint" wire:loading wire:target="file">{{ __('platform.listings.checking') }}</span>
    <label class="app-checkbox"><input type="checkbox" wire:model="fetchMedia"> {{ __('platform.listings.cache_photos') }}</label>

    @if ($preview !== null)
        <div class="card-block" data-test="preview">
            <p class="font-semibold">{{ __('platform.listings.preview_summary', ['total' => $preview['total'], 'created' => $preview['created'], 'updated' => $preview['updated'], 'errors' => count($preview['errors'])]) }}</p>
            @if ($preview['errors'] !== [])
                <ul class="app-error-list">
                    @foreach (array_slice($preview['errors'], 0, 50) as $error)<li><span dir="ltr">#{{ $error['row'] }} {{ $error['ref'] }}</span> — {{ $error['error'] }}</li>@endforeach
                </ul>
            @endif
            <button type="button" class="btn-primary" wire:click="import" wire:loading.attr="disabled" data-test="import-button" @disabled($preview['created'] + $preview['updated'] === 0)>{{ __('platform.listings.import_now', ['count' => $preview['created'] + $preview['updated']]) }}</button>
        </div>
    @endif

    @if ($result !== null)
        <div class="card-block" role="status" data-test="result">
            <p class="app-ok-text">{{ __('platform.listings.import_done', ['created' => $result['created'], 'updated' => $result['updated'], 'errors' => count($result['errors'])]) }}</p>
            @if ($result['errors'] !== [])
                <ul class="app-error-list">
                    @foreach ($result['errors'] as $error)<li><span dir="ltr">#{{ $error['row'] }} {{ $error['ref'] }}</span> — {{ $error['error'] }}</li>@endforeach
                </ul>
            @endif
            <a href="{{ route('listings') }}" class="app-link">{{ __('platform.listings.title') }} →</a>
        </div>
    @endif
</div>
