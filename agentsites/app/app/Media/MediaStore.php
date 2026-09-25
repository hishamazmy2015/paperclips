<?php

declare(strict_types=1);

namespace App\Media;

use App\Models\Media;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant files on the `media` disk under {tenant_id}/ (spec §5, §17), one Media row each,
 * addressed by a content-hashed name so the public path can be cached forever.
 */
final class MediaStore
{
    public function put(Tenant $tenant, string $binary, string $basename, string $mime = 'image/webp'): Media
    {
        $sha = hash('sha256', $binary);
        $extension = match ($mime) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
        $path = $tenant->id.'/'.$basename.'-'.substr($sha, 0, 12).'.'.$extension;
        $this->disk()->put($path, $binary);

        $size = @getimagesizefromstring($binary);

        return TenantContext::with($tenant, fn (): Media => Media::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'path' => $path],
            [
                'disk' => 'media',
                'mime' => $mime,
                'size_bytes' => strlen($binary),
                'width' => $size !== false ? (int) $size[0] : null,
                'height' => $size !== false ? (int) $size[1] : null,
                'sha256' => $sha,
            ],
        ));
    }

    /** The path a site (or the app host) serves the file from — see MediaController. */
    public static function publicPath(Media $media): string
    {
        return '/media/'.$media->path;
    }

    public function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('media');

        return $disk;
    }
}
