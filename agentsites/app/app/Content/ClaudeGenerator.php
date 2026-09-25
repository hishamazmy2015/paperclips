<?php

declare(strict_types=1);

namespace App\Content;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use RuntimeException;

/**
 * Site copy from the Anthropic API (spec §6, §12.3): one structured-output request per tenant
 * for the template-marked fields, in the requested locales. It only ever runs from the queued
 * GenerateContent job, which keeps the template text on any failure — publishing never waits
 * for it (§22.3). Facts come from the tenant config; the model is told never to invent more.
 */
final class ClaudeGenerator implements ContentGenerator
{
    /** @var array<string, array{max: int, brief: string}> */
    public const FIELDS = [
        'identity.tagline' => ['max' => 90, 'brief' => 'one line, up to 90 characters: the agent\'s promise to clients'],
        'identity.bio' => ['max' => 320, 'brief' => 'two or three sentences, up to 320 characters, first person plural avoided; third person'],
        'content.about' => ['max' => 800, 'brief' => 'one warm paragraph, up to 800 characters, third person, ends with an invitation to message on WhatsApp'],
        'seo.meta_description' => ['max' => 155, 'brief' => 'a search-result description, up to 155 characters, mentions sale and rent and the areas'],
    ];

    private const SYSTEM = <<<'TEXT'
You write website copy for licensed real-estate agents in the United Arab Emirates.
Use only the facts you are given; never invent licences, awards, numbers, years, languages or areas.
Keep the agent's name and agency exactly as written. No emojis, no hashtags, no exclamation marks, no superlatives like "best".
English: clear, warm, professional, plain words. Arabic: Modern Standard Arabic that reads naturally to a Gulf reader — not a literal translation — with the same facts.
Return only the requested JSON object.
TEXT;

    public function __construct(private readonly Client $client, private readonly string $model = 'claude-opus-5') {}

    public function generate(array $config, array $fields, array $locales): array
    {
        $fields = array_values(array_intersect($fields, array_keys(self::FIELDS)));
        $locales = array_values(array_intersect($locales, ['ar', 'en']));
        if ($fields === [] || $locales === []) {
            return [];
        }

        $properties = [];
        $required = [];
        foreach ($fields as $field) {
            foreach ($locales as $locale) {
                $key = self::key($field, $locale);
                $properties[$key] = ['type' => 'string', 'description' => self::FIELDS[$field]['brief'].' — in '.($locale === 'ar' ? 'Arabic' : 'English')];
                $required[] = $key;
            }
        }

        $message = $this->client->beta->messages->create(
            model: $this->model,
            maxTokens: 4096,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $this->prompt($config, $fields, $locales)]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ]]],
            // a policy decline is retried server-side on the model's default fallback
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('content generation refused');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                /** @var BetaTextBlock $block */
                $text = $block->text;
                break;
            }
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($text, true, 8, JSON_THROW_ON_ERROR);

        $out = [];
        foreach ($fields as $field) {
            foreach ($locales as $locale) {
                $value = trim((string) ($data[self::key($field, $locale)] ?? ''));
                if ($value !== '') {
                    $out[$field][$locale] = mb_substr($value, 0, self::FIELDS[$field]['max'] + 40);
                }
            }
        }

        return $out;
    }

    public static function key(string $field, string $locale): string
    {
        return str_replace('.', '_', $field).'__'.$locale;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $fields
     * @param  list<string>  $locales
     */
    private function prompt(array $config, array $fields, array $locales): string
    {
        $facts = array_filter([
            'name' => (string) data_get($config, 'identity.display_name', ''),
            'agency' => (string) data_get($config, 'identity.agency_name', ''),
            'licensed' => data_get($config, 'identity.license_no', '') !== '' || data_get($config, 'identity.brn', '') !== '' ? 'yes (do not quote the number)' : '',
            'years_experience' => (int) data_get($config, 'identity.years_experience', 0) > 0 ? (string) data_get($config, 'identity.years_experience') : '',
            'languages' => implode(', ', array_map('strval', (array) data_get($config, 'identity.languages', []))),
            'service_areas' => implode(', ', array_map('strval', (array) data_get($config, 'content.service_areas', []))) ?: 'Dubai',
            'specialties' => implode(', ', array_map('strval', (array) data_get($config, 'content.specialties', []))),
        ], static fn (string $value): bool => $value !== '');

        $lines = ['Facts about the agent:'];
        foreach ($facts as $key => $value) {
            $lines[] = "- {$key}: {$value}";
        }
        $lines[] = '';
        $lines[] = 'Write these fields, one JSON key per field and locale ('.implode(', ', $locales).'):';
        foreach ($fields as $field) {
            foreach ($locales as $locale) {
                $lines[] = '- '.self::key($field, $locale).': '.self::FIELDS[$field]['brief'];
            }
        }

        return implode("\n", $lines);
    }
}
