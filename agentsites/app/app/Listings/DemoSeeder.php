<?php

declare(strict_types=1);

namespace App\Listings;

use App\Models\Listing;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

/**
 * Demo listings so a brand-new site never looks empty (spec §12.3, §14). They are shown until
 * real listings exist and never carry structured data.
 */
final class DemoSeeder
{
    /** @var list<array{type: string, offering: string, beds: int, baths: int, sqft: int, price: int, en: string, ar: string, den: string, dar: string}> */
    private const TEMPLATES = [
        ['type' => 'apartment', 'offering' => 'sale', 'beds' => 2, 'baths' => 2, 'sqft' => 1350, 'price' => 2450000, 'en' => 'Bright 2BR with skyline views', 'ar' => 'شقة مشرقة بغرفتين وإطلالة على الأفق', 'den' => 'Corner unit with floor-to-ceiling glass, upgraded kitchen and two parking bays.', 'dar' => 'وحدة زاوية بزجاج من الأرض إلى السقف ومطبخ محدّث وموقفين للسيارات.'],
        ['type' => 'apartment', 'offering' => 'rent', 'beds' => 1, 'baths' => 1, 'sqft' => 820, 'price' => 95000, 'en' => 'Furnished 1BR steps from the promenade', 'ar' => 'شقة مفروشة بغرفة واحدة على بعد خطوات من الممشى', 'den' => 'Fully furnished, chiller free, available immediately.', 'dar' => 'مفروشة بالكامل، التبريد مجاني، متاحة فوراً.'],
        ['type' => 'villa', 'offering' => 'sale', 'beds' => 4, 'baths' => 5, 'sqft' => 4200, 'price' => 7900000, 'en' => '4BR villa on the park', 'ar' => 'فيلا 4 غرف على الحديقة', 'den' => 'Private pool, maid\'s room, single-row plot facing the green.', 'dar' => 'مسبح خاص، غرفة خادمة، قطعة أرض بصف واحد مقابل المساحات الخضراء.'],
        ['type' => 'townhouse', 'offering' => 'rent', 'beds' => 3, 'baths' => 4, 'sqft' => 2300, 'price' => 185000, 'en' => 'Upgraded 3BR townhouse, vacant', 'ar' => 'تاون هاوس 3 غرف محدّث وشاغر', 'den' => 'Landscaped garden, covered parking, near the community pool.', 'dar' => 'حديقة منسقة، موقف مغطى، قرب مسبح المجمع.'],
        ['type' => 'penthouse', 'offering' => 'sale', 'beds' => 3, 'baths' => 4, 'sqft' => 3100, 'price' => 9600000, 'en' => 'Penthouse with wraparound terrace', 'ar' => 'بنتهاوس بشرفة محيطة', 'den' => 'Top floor, private lift lobby, panoramic sunset views.', 'dar' => 'الطابق الأخير، ردهة مصعد خاصة، إطلالات بانورامية على الغروب.'],
        ['type' => 'studio', 'offering' => 'sale', 'beds' => 0, 'baths' => 1, 'sqft' => 480, 'price' => 690000, 'en' => 'Investor studio, 8% yield', 'ar' => 'استوديو للمستثمرين بعائد 8%', 'den' => 'Tenanted until next year, managed building, high rental demand.', 'dar' => 'مؤجر حتى العام القادم، مبنى مُدار، طلب إيجاري مرتفع.'],
    ];

    /** Seeds demo listings unless the tenant already has any; returns how many were created. */
    public function seed(Tenant $tenant): int
    {
        return TenantContext::with($tenant, function (Tenant $tenant): int {
            if (Listing::query()->exists()) {
                return 0;
            }

            /** @var list<string> $areas */
            $areas = array_values((array) data_get($tenant->mergedConfig(), 'content.service_areas', []));
            if ($areas === []) {
                $areas = ['Downtown', 'Marina', 'Business Bay'];
            }
            $city = in_array($areas[0], ['Yas Island', 'Saadiyat', 'Al Reem', 'Khalifa City'], true) ? 'Abu Dhabi' : 'Dubai';

            $created = 0;
            foreach (self::TEMPLATES as $i => $t) {
                $area = $areas[$i % count($areas)];
                Listing::query()->create([
                    'ref' => 'DEMO-'.($i + 1),
                    'title_en' => $t['en'].' in '.$area,
                    'title_ar' => $t['ar'].' في '.$area,
                    'description_en' => $t['den'],
                    'description_ar' => $t['dar'],
                    'offering' => $t['offering'],
                    'property_type' => $t['type'],
                    'price' => $t['price'],
                    'currency' => 'AED',
                    'bedrooms' => $t['beds'],
                    'bathrooms' => $t['baths'],
                    'area_sqft' => $t['sqft'],
                    'community' => $area,
                    'city' => $city,
                    'status' => Listing::STATUS_AVAILABLE,
                    'source' => Listing::SOURCE_DEMO,
                    'featured' => $i < 3,
                    'media' => ['/demo/listing-'.($i + 1).'.svg'],
                ]);
                $created++;
            }

            return $created;
        });
    }
}
