<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Fills missing tenant copy (tagline, bio, about, why_me, meta description) in every
 * requested locale (spec §6, §12.3). Implementations: ClaudeGenerator (Anthropic API)
 * and TemplateGenerator, the fallback that never fails — publishing is never blocked by
 * an external API (spec §22.3).
 */
interface ContentGenerator
{
    /**
     * @param  array<string, mixed>  $config  tenant config with defaults merged
     * @param  list<string>  $fields  dotted config keys to generate, e.g. "identity.tagline"
     * @param  list<string>  $locales  e.g. ['ar', 'en']
     * @return array<string, array<string, string>> field => locale => text
     */
    public function generate(array $config, array $fields, array $locales): array;
}
