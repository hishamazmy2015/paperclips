<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Billing\PlanLimits;
use App\Listings\ListingCsv;
use App\Listings\ListingPhotos;
use App\Livewire\Concerns\OwnsTenant;
use App\Models\Listing;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Create / edit one listing (spec §14): multi-photo upload with drag order, AED prices,
 * community autocomplete (config/communities.php), map pin as coordinates. Validation is the
 * same ListingCsv::normalize the importer and the feeds use.
 */
#[Layout('components.layouts.app')]
final class Form extends Component
{
    use OwnsTenant, WithFileUploads;

    public ?int $listingId = null;

    public string $ref = '';

    public string $title_en = '';

    public string $title_ar = '';

    public string $description_en = '';

    public string $description_ar = '';

    public string $offering = 'sale';

    public string $property_type = 'apartment';

    public string $price = '';

    public string $currency = 'AED';

    public string $bedrooms = '';

    public string $bathrooms = '';

    public string $area_sqft = '';

    public string $community = '';

    public string $city = 'Dubai';

    public string $status = 'available';

    public bool $featured = false;

    public string $lat = '';

    public string $lng = '';

    /** @var list<array<string, mixed>|string> existing media entries in display order */
    public array $media = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    /** @var list<string> */
    public array $problems = [];

    public string $photoError = '';

    public function mount(?int $listing = null): void
    {
        $tenant = $this->tenant();
        if ($listing === null) {
            $count = TenantContext::with($tenant, static fn (): int => Listing::query()->real()->count());
            $this->ref = 'REF-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

            return;
        }
        $model = TenantContext::with($tenant, static fn (): ?Listing => Listing::query()->whereKey($listing)->real()->first());
        if ($model === null) {
            abort(404);
        }
        $this->listingId = $model->id;
        $this->ref = $model->ref;
        $this->title_en = (string) $model->title_en;
        $this->title_ar = (string) $model->title_ar;
        $this->description_en = (string) $model->description_en;
        $this->description_ar = (string) $model->description_ar;
        $this->offering = (string) $model->offering;
        $this->property_type = (string) $model->property_type;
        $this->price = rtrim(rtrim((string) $model->price, '0'), '.');
        $this->currency = (string) $model->currency;
        $this->bedrooms = $model->bedrooms === null ? '' : (string) $model->bedrooms;
        $this->bathrooms = $model->bathrooms === null ? '' : (string) $model->bathrooms;
        $this->area_sqft = $model->area_sqft === null ? '' : rtrim(rtrim((string) $model->area_sqft, '0'), '.');
        $this->community = (string) $model->community;
        $this->city = (string) $model->city;
        $this->status = (string) $model->status;
        $this->featured = (bool) $model->featured;
        $this->lat = $model->lat === null ? '' : (string) $model->lat;
        $this->lng = $model->lng === null ? '' : (string) $model->lng;
        /** @var list<array<string, mixed>|string> $media */
        $media = $model->getAttribute('media') ?? [];
        $this->media = $media;
    }

    public function updatedPhotos(): void
    {
        $this->photoError = '';
        $photos = app(ListingPhotos::class);
        foreach ($this->photos as $upload) {
            try {
                $this->media[] = $photos->store($this->tenant(), $this->ref !== '' ? $this->ref : 'new', (string) file_get_contents($upload->getRealPath()));
            } catch (Throwable $e) {
                $this->photoError = __('platform.wizard.image_rejected');
            } finally {
                $upload->delete();
            }
        }
        $this->photos = [];
    }

    public function move(int $index, int $delta): void
    {
        $target = $index + $delta;
        if (! isset($this->media[$index]) || $target < 0 || $target >= count($this->media)) {
            return;
        }
        [$this->media[$index], $this->media[$target]] = [$this->media[$target], $this->media[$index]];
        $this->media = array_values($this->media);
    }

    /** @param  list<int>  $order  indexes in their new order (drag and drop) */
    public function reorder(array $order): void
    {
        $reordered = [];
        foreach ($order as $index) {
            if (isset($this->media[(int) $index])) {
                $reordered[] = $this->media[(int) $index];
            }
        }
        if (count($reordered) === count($this->media)) {
            $this->media = $reordered;
        }
    }

    public function removePhoto(int $index): void
    {
        unset($this->media[$index]);
        $this->media = array_values($this->media);
    }

    public function save(): void
    {
        $tenant = $this->tenant();
        $normalized = ListingCsv::normalize([
            'ref' => $this->ref, 'title_en' => $this->title_en, 'title_ar' => $this->title_ar, 'description_en' => $this->description_en, 'description_ar' => $this->description_ar,
            'offering' => $this->offering, 'property_type' => $this->property_type, 'price' => $this->price, 'currency' => $this->currency, 'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms, 'area_sqft' => $this->area_sqft, 'community' => $this->community, 'city' => $this->city, 'status' => $this->status, 'featured' => $this->featured ? 'true' : '',
        ]);
        $this->problems = $normalized['errors'];
        $lat = trim($this->lat) === '' ? null : (float) $this->lat;
        $lng = trim($this->lng) === '' ? null : (float) $this->lng;
        if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs((float) $lng) > 180))) {
            $this->problems[] = __('platform.listings.pin_invalid');
        }
        if ($this->problems !== []) {
            return;
        }

        $saved = TenantContext::with($tenant, function () use ($tenant, $normalized, $lat, $lng): bool {
            $listing = $this->listingId !== null ? Listing::query()->whereKey($this->listingId)->real()->first() : null;
            $duplicate = Listing::query()->where('ref', (string) $normalized['attributes']['ref'])->when($listing !== null, fn ($q) => $q->where('id', '!=', $listing->id))->exists();
            if ($duplicate) {
                $this->problems[] = __('platform.listings.ref_taken');

                return false;
            }
            if ($listing === null && ! PlanLimits::canAddListings($tenant)) {
                $this->problems[] = __('platform.listings.limit_reached', ['limit' => PlanLimits::limit($tenant->account, 'listings')]);

                return false;
            }
            $listing ??= new Listing(['tenant_id' => $tenant->id, 'source' => 'manual']);
            $listing->fill($normalized['attributes'] + ['lat' => $lat, 'lng' => $lng, 'media' => $this->media]);
            $listing->save();
            $this->listingId = $listing->id;

            return true;
        });

        if ($saved) {
            session()->flash('status', __('platform.listings.saved'));
            $this->redirectRoute('listings');
        }
    }

    public function render(): View
    {
        $sets = (new Listing(['media' => $this->media]))->imageSets();
        /** @var array<string, list<string>> $communities */
        $communities = config('communities', []);

        return view('livewire.listings.form', [
            'sets' => $sets,
            'types' => ListingCsv::TYPES,
            'statuses' => ListingCsv::STATUSES,
            'communities' => $communities,
        ])->title(__($this->listingId === null ? 'platform.listings.new' : 'platform.listings.edit'));
    }
}
