<div class="app-card" data-test="listing-form">
    <x-app-nav active="listings" />
    <h1 class="app-h1">{{ __($listingId === null ? 'platform.listings.new' : 'platform.listings.edit') }}</h1>

    @if ($problems !== [])
        <ul class="app-error" role="alert" data-test="problems">
            @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
        </ul>
    @endif

    <form wire:submit="save" class="wizard-fields">
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.ref') }}</span><input type="text" class="app-input" dir="ltr" wire:model="ref" maxlength="80" required data-test="ref"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.status') }}</span>
                <select class="app-input" wire:model="status" data-test="status">@foreach ($statuses as $s)<option value="{{ $s }}">{{ __('platform.listings.status_'.$s) }}</option>@endforeach</select></label>
        </div>
        <label class="app-field"><span class="app-label">{{ __('platform.listings.title_en') }}</span><input type="text" class="app-input" wire:model="title_en" maxlength="200" required data-test="title_en"></label>
        <label class="app-field"><span class="app-label">{{ __('platform.listings.title_ar') }}</span><input type="text" class="app-input" dir="rtl" wire:model="title_ar" maxlength="200" data-test="title_ar"></label>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.offering') }}</span>
                <select class="app-input" wire:model="offering" data-test="offering"><option value="sale">{{ __('site.listing.for_sale') }}</option><option value="rent">{{ __('site.listing.for_rent') }}</option></select></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.type') }}</span>
                <select class="app-input" wire:model="property_type" data-test="property_type">@foreach ($types as $t)<option value="{{ $t }}">{{ __('site.listing.type.'.$t, [], null) !== 'site.listing.type.'.$t ? __('site.listing.type.'.$t) : ucfirst($t) }}</option>@endforeach</select></label>
        </div>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.price') }} (AED)</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="price" required data-test="price"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.area_sqft') }}</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="area_sqft" data-test="area_sqft"></label>
        </div>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.bedrooms') }}</span><input type="number" min="0" max="30" class="app-input" dir="ltr" wire:model="bedrooms" data-test="bedrooms"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.bathrooms') }}</span><input type="number" min="0" max="30" class="app-input" dir="ltr" wire:model="bathrooms" data-test="bathrooms"></label>
        </div>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.community') }}</span><input type="text" class="app-input" list="communities" wire:model="community" maxlength="120" data-test="community">
                <datalist id="communities">@foreach ($communities as $city => $names)@foreach ($names as $name)<option value="{{ $name }}">{{ $city }}</option>@endforeach @endforeach</datalist></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.city') }}</span><input type="text" class="app-input" wire:model="city" maxlength="80" data-test="city"></label>
        </div>
        <div class="app-grid-2">
            <label class="app-field"><span class="app-label">{{ __('platform.listings.lat') }}</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="lat" placeholder="25.1972" data-test="lat"></label>
            <label class="app-field"><span class="app-label">{{ __('platform.listings.lng') }}</span><input type="text" inputmode="decimal" class="app-input" dir="ltr" wire:model="lng" placeholder="55.2744" data-test="lng"></label>
        </div>
        <p class="app-fineprint">{{ __('platform.listings.pin_hint') }}</p>
        <label class="app-field"><span class="app-label">{{ __('platform.listings.description_en') }}</span><textarea class="app-input" rows="4" wire:model="description_en" data-test="description_en"></textarea></label>
        <label class="app-field"><span class="app-label">{{ __('platform.listings.description_ar') }}</span><textarea class="app-input" rows="4" dir="rtl" wire:model="description_ar" data-test="description_ar"></textarea></label>
        <label class="app-checkbox"><input type="checkbox" wire:model="featured" data-test="featured"> {{ __('platform.listings.featured') }}</label>

        <p class="app-label">{{ __('platform.listings.photos') }}</p>
        <ul class="photo-grid" x-data="{ from: null }" data-test="photos">
            @foreach ($sets as $i => $set)
                <li class="photo-tile" wire:key="photo-{{ $i }}-{{ md5($set['thumb']) }}" draggable="true"
                    x-on:dragstart="from = {{ $i }}" x-on:dragover.prevent x-on:drop.prevent="if (from !== null && from !== {{ $i }}) { const order = [...Array({{ count($sets) }}).keys()]; order.splice(from, 1); order.splice({{ $i }}, 0, from); $wire.reorder(order); } from = null">
                    <img src="{{ $set['thumb'] }}" alt="" width="96" height="96">
                    <span class="photo-tile-actions">
                        <button type="button" wire:click="move({{ $i }}, -1)" aria-label="{{ __('platform.listings.move_up') }}" data-test="photo-up-{{ $i }}">‹</button>
                        <button type="button" wire:click="move({{ $i }}, 1)" aria-label="{{ __('platform.listings.move_down') }}" data-test="photo-down-{{ $i }}">›</button>
                        <button type="button" wire:click="removePhoto({{ $i }})" aria-label="{{ __('platform.listings.remove_photo') }}" data-test="photo-remove-{{ $i }}">×</button>
                    </span>
                    @if ($i === 0)<span class="photo-cover">{{ __('platform.listings.cover') }}</span>@endif
                </li>
            @endforeach
            <li class="photo-tile photo-add">
                <label>
                    <span>+ {{ __('platform.listings.add_photos') }}</span>
                    <input type="file" accept="image/*" multiple class="sr-only" wire:model="photos" data-test="photo-input">
                </label>
            </li>
        </ul>
        <span class="app-fineprint" wire:loading wire:target="photos">{{ __('platform.wizard.uploading') }}</span>
        @if ($photoError !== '')<p class="app-error" role="alert">{{ $photoError }}</p>@endif

        <div class="wizard-nav">
            <button type="submit" class="btn-primary" data-test="save">{{ __('platform.listings.save') }}</button>
            <a href="{{ route('listings') }}" class="btn-secondary">{{ __('platform.wizard.back') }}</a>
        </div>
    </form>
</div>
