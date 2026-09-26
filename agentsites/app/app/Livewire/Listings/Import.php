<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Listings\ListingImporter;
use App\Livewire\Concerns\OwnsTenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** CSV import with the template and per-row errors (spec §14): upload → check → import. */
#[Layout('components.layouts.app')]
final class Import extends Component
{
    use OwnsTenant, WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public bool $fetchMedia = true;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function updatedFile(): void
    {
        $this->preview = null;
        $this->result = null;
        if ($this->file === null) {
            return;
        }
        $this->preview = app(ListingImporter::class)->importCsv($this->tenant(), $this->file->getRealPath(), dryRun: true);
    }

    public function import(): void
    {
        if ($this->file === null) {
            return;
        }
        $this->result = app(ListingImporter::class)->importCsv($this->tenant(), $this->file->getRealPath(), dryRun: false, fetchMedia: $this->fetchMedia);
        $this->preview = null;
        $this->file->delete();
        $this->file = null;
    }

    public function render(): View
    {
        return view('livewire.listings.import')->title(__('platform.listings.import'));
    }
}
