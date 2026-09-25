<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Themes\ThemeRegistry;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Deterministic synthetic agents for scale tests and demos (goal G1: 1000+ sites from one
 * codebase). Every row is a different agent — Arabic and Latin names, agencies, areas, theme,
 * palette, locale, languages — so every generated site renders differently. Same seed, same CSV.
 */
final class AgentGenerator
{
    /** @var list<string> */
    private const FIRST_AR = ['Ahmed', 'Mohammed', 'Khalid', 'Saeed', 'Rashid', 'Hamad', 'Sultan', 'Abdullah', 'Omar', 'Youssef', 'Fatima', 'Mariam', 'Aisha', 'Noura', 'Hessa', 'Sara', 'Layla', 'Reem', 'Salama', 'Alia', 'Tariq', 'Faisal', 'Majid', 'Hind', 'Shamsa'];

    /** @var list<string> */
    private const LAST_AR = ['Al Falasi', 'Al Mansoori', 'Al Suwaidi', 'Al Mazrouei', 'Al Ketbi', 'Al Hammadi', 'Al Shamsi', 'Al Marzooqi', 'Al Nuaimi', 'Al Zaabi', 'Al Qubaisi', 'Al Dhaheri', 'Al Muhairi', 'Al Blooshi', 'Al Ameri'];

    /** @var list<string> */
    private const NAMES_ARABIC_SCRIPT = ['أحمد الفلاسي', 'سارة المنصوري', 'خالد السويدي', 'مريم المزروعي', 'سعيد الكتبي', 'نورة الحمادي', 'راشد الشامسي', 'عائشة المرزوقي', 'حمد النعيمي', 'حصة الزعابي', 'سلطان القبيسي', 'ريم الظاهري', 'عمر المهيري', 'ليلى البلوشي', 'يوسف العامري'];

    /** @var list<string> */
    private const NAMES_OTHER = ['Priya Sharma', 'Rahul Mehta', 'Anjali Nair', 'John Smith', 'Sophie Turner', 'Elena Petrova', 'Dmitry Volkov', 'Chen Wei', 'Farah Khan', 'Bilal Ahmed', 'Zainab Hussain', 'Omar Farouk', 'Nadia Haddad', 'Karim Aziz', 'Maria Santos', 'Lucas Ferreira', 'Aylin Demir', 'Hassan Raza', 'Grace Okafor', 'Daniel Cohen', 'Amira Saleh', 'Vikram Singh', 'Leila Nasser', 'Tom Becker', 'Yara Haddad'];

    /** @var list<string> */
    private const AGENCIES = ['Palm Crest Realty', 'Marina Bay Properties', 'Downtown Keys', 'Sandstone Estates', 'Blue Water Homes', 'Skyline Brokers', 'Oasis Living', 'Pearl Coast Realty', 'Emirates Nest', 'Gulf Gate Properties', 'Horizon Realty', 'Desert Rose Homes', 'Meydan Living', 'Creek View Estates', 'Al Reem Partners', 'Yas Bay Realty', 'Saadiyat Living', 'Jumeirah Collective', 'Hills Homes', 'Bay Square Brokers'];

    /** @var list<string> */
    private const AREAS = ['Downtown', 'Marina', 'Palm Jumeirah', 'JVC', 'Business Bay', 'Dubai Hills', 'Yas Island', 'Saadiyat', 'Al Reem', 'Khalifa City', 'JLT', 'Arabian Ranches', 'Damac Hills', 'Mirdif', 'Al Furjan', 'Bluewaters', 'Jumeirah', 'Umm Suqeim', 'Al Barsha', 'Motor City', 'Sports City', 'Silicon Oasis', 'Dubai Creek Harbour', 'Sobha Hartland', 'MBR City'];

    /** @var list<string> */
    private const PALETTES = ['sand', 'navy', 'emerald', 'charcoal', 'rose', 'gold'];

    /** @var list<list<string>> */
    private const LANGUAGES = [['ar', 'en'], ['en'], ['ar', 'en', 'hi'], ['en', 'ru'], ['ar', 'en', 'fr'], ['en', 'hi', 'ur'], ['ar', 'en', 'zh']];

    public function __construct(private readonly ThemeRegistry $themes) {}

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function generate(int $count, int $seed = 1): \Generator
    {
        $random = new Randomizer(new Xoshiro256StarStar($seed));
        $themes = $this->themes->installed();
        $phones = [];

        for ($i = 1; $i <= $count; $i++) {
            $kind = $random->getInt(1, 100);
            $name = match (true) {
                $kind <= 20 => self::pick($random, self::NAMES_ARABIC_SCRIPT),
                $kind <= 65 => self::pick($random, self::FIRST_AR).' '.self::pick($random, self::LAST_AR),
                default => self::pick($random, self::NAMES_OTHER),
            };

            do {
                $phone = '+9715'.self::pick($random, ['0', '2', '4', '5', '6', '8']).str_pad((string) $random->getInt(0, 9_999_999), 7, '0', STR_PAD_LEFT);
            } while (isset($phones[$phone]));
            $phones[$phone] = true;

            $areaCount = $random->getInt(1, 4);
            $areas = array_values(array_unique(array_map(fn (): string => self::pick($random, self::AREAS), range(1, $areaCount))));
            $arabic = $kind <= 20 || $random->getInt(1, 100) <= 30;

            yield $i => [
                'name' => $name,
                'whatsapp' => $phone,
                'agency' => $random->getInt(1, 100) <= 85 ? self::pick($random, self::AGENCIES) : '',
                'license' => $random->getInt(1, 100) <= 60 ? 'BRN-'.$random->getInt(10000, 99999) : '',
                'slug' => '',
                'theme' => self::pick($random, $themes),
                'areas' => $areas,
                'email' => $random->getInt(1, 100) <= 80 ? 'agent-'.$i.'@example.com' : '',
                'locale' => $arabic ? 'ar' : 'en',
                'palette' => self::pick($random, self::PALETTES),
                'years' => $random->getInt(0, 25),
                'languages' => self::pick($random, self::LANGUAGES),
                'instagram' => $random->getInt(1, 100) <= 50 ? 'agent'.$i.'homes' : '',
                'dark_mode' => self::pick($random, ['auto', 'auto', 'auto', 'on', 'off']),
                'photo' => '',
                'tagline_en' => '',
                'tagline_ar' => '',
                'bio_en' => '',
                'bio_ar' => '',
            ];
        }
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private static function pick(Randomizer $random, array $items): mixed
    {
        return $items[$random->getInt(0, count($items) - 1)];
    }
}
