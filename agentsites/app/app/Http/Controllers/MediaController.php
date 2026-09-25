<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Media\MediaStore;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /media/{tenant_id}/{file} (spec §17): a tenant host only serves its own directory; the app
 * host serves the signed-in owner's sites. Names are content-hashed, so responses are immutable.
 */
final class MediaController extends Controller
{
    public function __invoke(Request $request, MediaStore $store, string $path): BinaryFileResponse
    {
        if (preg_match('#^(\d+)/([A-Za-z0-9._-]+)$#', $path, $m) !== 1) {
            abort(404);
        }
        $tenantId = (int) $m[1];

        $tenant = TenantContext::current();
        if ($tenant !== null) {
            abort_if($tenant->id !== $tenantId, 404);
        } else {
            $user = $request->user();
            abort_if($user === null || ! Tenant::query()->whereKey($tenantId)->where('account_id', $user->account_id)->exists(), 404);
        }

        $disk = $store->disk();
        abort_if(! $disk->exists($path), 404);

        return new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => (string) ($disk->mimeType($path) ?: 'application/octet-stream'),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
