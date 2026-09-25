<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Listing;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Platform\Hosts;
use App\Tenancy\TenantContext;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** S5 (spec §13): confetti, the URL, a QR code, WhatsApp share, open, and the checklist with % done. */
final class SuccessController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $tenant = Tenant::query()->where('account_id', $request->user()?->getAttribute('account_id'))->orderBy('id')->first();
        if ($tenant === null) {
            return redirect()->route('start');
        }
        if (! $tenant->isLive()) {
            return redirect()->route('onboarding');
        }

        $url = Hosts::browserUrl($tenant->primaryHost(), '/');
        $checklist = self::checklist($tenant);
        $done = count(array_filter($checklist));

        return view('app.success', [
            'tenant' => $tenant,
            'url' => $url,
            'qr' => $this->qr($url),
            'checklist' => $checklist,
            'percent' => (int) round($done / max(1, count($checklist)) * 100),
        ]);
    }

    /**
     * The five next steps and whether each is done (spec §13 S5).
     *
     * @return array<string, bool>
     */
    public static function checklist(Tenant $tenant): array
    {
        $config = $tenant->mergedConfig();

        return TenantContext::with($tenant, static fn (): array => [
            'listings' => Listing::query()->real()->exists(),
            'domain' => Domain::query()->where('type', Domain::TYPE_CUSTOM)->exists(),
            'testimonials' => Testimonial::query()->exists(),
            'instagram' => (string) data_get($config, 'contact.socials.instagram', '') !== '',
            'logo' => (string) data_get($config, 'identity.logo', '') !== '',
        ]);
    }

    private function qr(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd)))->writeString($url);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
