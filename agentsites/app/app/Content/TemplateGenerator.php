<?php

declare(strict_types=1);

namespace App\Content;

/**
 * The fallback ContentGenerator (spec §6, §12.3): bilingual templates filled from the tenant's
 * own facts. It never calls anything, never throws, and always returns text — a site created
 * with only a name and a WhatsApp number renders complete.
 */
final class TemplateGenerator implements ContentGenerator
{
    /** @var array<string, array<string, list<string>>> field => locale => templates */
    private const TEMPLATES = [
        'identity.tagline' => [
            'en' => ['{name} — your real estate partner in {area}.', 'Helping you buy, sell and rent in {area}.'],
            'ar' => ['{name} — شريكك العقاري في {area}.', 'أساعدك في البيع والشراء والإيجار في {area}.'],
        ],
        'identity.bio' => [
            'en' => ['{name}{agency_phrase} works with buyers, sellers and tenants across {areas}. Straight answers, local knowledge and a fast reply on WhatsApp — every time.'],
            'ar' => ['{name}{agency_phrase_ar} يعمل مع المشترين والبائعين والمستأجرين في {areas_ar}. إجابات واضحة، معرفة محلية، ورد سريع على واتساب — دائماً.'],
        ],
        'content.about' => [
            'en' => ['Whether you are buying your first home, investing, or looking for the right rental, {name} guides you through every step: shortlisting the right communities in {areas}, viewings, negotiation and paperwork. Message on WhatsApp to get started.'],
            'ar' => ['سواء كنت تشتري منزلك الأول، أو تستثمر، أو تبحث عن الإيجار المناسب، يرافقك {name} في كل خطوة: اختيار المناطق المناسبة في {areas_ar}، والمعاينات، والتفاوض، والأوراق. راسلني على واتساب لنبدأ.'],
        ],
        'seo.meta_description' => [
            'en' => ['{name}{agency_phrase}: properties for sale and rent in {areas}. Contact on WhatsApp for viewings and advice.'],
            'ar' => ['{name}{agency_phrase_ar}: عقارات للبيع والإيجار في {areas_ar}. تواصل عبر واتساب للمعاينة والاستشارة.'],
        ],
    ];

    /** @var array<int, array{title: array{en: string, ar: string}, text: array{en: string, ar: string}, icon: string}> */
    private const WHY_ME = [
        ['title' => ['en' => 'Local expertise', 'ar' => 'خبرة محلية'], 'text' => ['en' => 'Deep knowledge of {areas} — prices, buildings and what is really worth viewing.', 'ar' => 'معرفة عميقة بمناطق {areas_ar} — الأسعار والمباني وما يستحق المعاينة فعلاً.'], 'icon' => 'map'],
        ['title' => ['en' => 'Fast replies', 'ar' => 'ردود سريعة'], 'text' => ['en' => 'Message on WhatsApp and get an answer the same day.', 'ar' => 'راسلني على واتساب واحصل على رد في اليوم نفسه.'], 'icon' => 'chat'],
        ['title' => ['en' => 'End-to-end support', 'ar' => 'دعم من البداية للنهاية'], 'text' => ['en' => 'From the first viewing to the keys: negotiation, paperwork and handover.', 'ar' => 'من أول معاينة حتى استلام المفاتيح: التفاوض والأوراق والتسليم.'], 'icon' => 'key'],
    ];

    /** @var array<string, string> */
    private const AREA_AR = [
        'downtown' => 'داون تاون', 'marina' => 'المارينا', 'palm jumeirah' => 'نخلة جميرا', 'jvc' => 'قرية جميرا الدائرية',
        'business bay' => 'الخليج التجاري', 'dubai hills' => 'دبي هيلز', 'yas island' => 'جزيرة ياس', 'saadiyat' => 'السعديات',
        'al reem' => 'الريم', 'khalifa city' => 'مدينة خليفة', 'dubai' => 'دبي', 'abu dhabi' => 'أبوظبي',
    ];

    public function generate(array $config, array $fields, array $locales): array
    {
        $vars = $this->variables($config);
        $out = [];

        foreach ($fields as $field) {
            foreach ($locales as $locale) {
                $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
                if ($field === 'content.why_me') {
                    continue;
                }
                $templates = self::TEMPLATES[$field][$locale] ?? null;
                if ($templates === null) {
                    continue;
                }
                $index = abs(crc32((string) ($config['identity']['display_name'] ?? ''))) % count($templates);
                $out[$field][$locale] = $this->fill($templates[$index], $vars);
            }
        }

        return $out;
    }

    /**
     * The "why me" block (structured, not a string per locale).
     *
     * @param  array<string, mixed>  $config
     * @return list<array{title: array{en: string, ar: string}, text: array{en: string, ar: string}, icon: string}>
     */
    public function whyMe(array $config): array
    {
        $vars = $this->variables($config);
        $items = [];
        foreach (self::WHY_ME as $item) {
            $items[] = [
                'title' => $item['title'],
                'text' => ['en' => $this->fill($item['text']['en'], $vars), 'ar' => $this->fill($item['text']['ar'], $vars)],
                'icon' => $item['icon'],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function variables(array $config): array
    {
        $name = trim((string) ($config['identity']['display_name'] ?? ''));
        $agency = trim((string) ($config['identity']['agency_name'] ?? ''));
        /** @var list<string> $areas */
        $areas = array_values(array_filter(array_map('strval', (array) ($config['content']['service_areas'] ?? []))));
        if ($areas === []) {
            $areas = ['Dubai'];
        }
        $areasAr = array_map(fn (string $a): string => self::AREA_AR[strtolower($a)] ?? $a, $areas);

        return [
            '{name}' => $name,
            '{agency}' => $agency,
            '{agency_phrase}' => $agency !== '' ? " of {$agency}" : '',
            '{agency_phrase_ar}' => $agency !== '' ? " من {$agency}" : '',
            '{area}' => $areas[0],
            '{area_ar}' => $areasAr[0],
            '{areas}' => $this->join($areas, 'and'),
            '{areas_ar}' => $this->join($areasAr, 'و'),
        ];
    }

    /** @param  list<string>  $items */
    private function join(array $items, string $and): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode($and === '، ' ? '، ' : ', ', $items).($and === 'و' ? ' و' : " {$and} ").$last;
    }

    /** @param  array<string, string>  $vars */
    private function fill(string $template, array $vars): string
    {
        return trim(strtr($template, $vars));
    }
}
