<?php

namespace App\Support\Seo;

use App\Models\EventTemplate;
use App\Models\SeoSetting;
use Illuminate\Support\Arr;

class MetaTagsResolver
{
    public static function resolve(array $context = []): array
    {
        $org = SeoSetting::organization();

        $meta = [];
        if (isset($context['seo']) && is_array($context['seo'])) {
            $meta = $context['seo'];
        } elseif (isset($context['seo']) && is_object($context['seo'])) {
            $meta = $context['seo']->seo_meta ?? [];
        }

        $template = self::resolveEventTemplate($context);

        $title = $meta['title']
            ?? Arr::get($context, 'pageTitle')
            ?? data_get($context, 'blogPost.title')
            ?? data_get($context, 'post.title')
            ?? $template?->seo_title
            ?? $template?->name
            ?? data_get($context, 'package.name')
            ?? data_get($context, 'item.name');

        $description = $meta['description']
            ?? Arr::get($context, 'pageDescription')
            ?? data_get($context, 'blogPost.excerpt')
            ?? data_get($context, 'post.excerpt')
            ?? self::plainText($template?->seo_description)
            ?? self::plainText($template?->event_description ?? null)
            ?? data_get($context, 'package.excerpt');

        $image = $meta['image']
            ?? data_get($context, 'blogPost.featured_image')
            ?? data_get($context, 'post.featured_image')
            ?? $template?->seo_og_image
            ?? $template?->featured_image
            ?? data_get($context, 'package.featured_image');

        $canonical = $meta['canonical']
            ?? Arr::get($context, 'canonical')
            ?? ($template ? $template->prettyUrl(Arr::get($context, 'start_place_id')) : null)
            ?? request()->getUri();

        $keywords = $meta['keywords'] ?? null;

        if (! $keywords) {
            $keywords = data_get($context, 'blogPost.seo_keywords')
                ?? data_get($context, 'post.seo_keywords')
                ?? $template?->seo_keywords
                ?? data_get($context, 'package.seo_keywords');
        }

        $baseKeywords = collect([
            'biuro podróży rafa',
            'wycieczki szkolne',
            'turystyka szkolna',
            'wyjazdy firmowe',
            'wyjazdy integracyjne',
            'zielone szkoły',
            'bprafa.pl',
        ]);

        $keywordsCollection = $keywords
            ? collect(preg_split('/\s*,\s*/', (string) $keywords, -1, PREG_SPLIT_NO_EMPTY))
            : collect();

        if ($image && ! str_starts_with((string) $image, 'http')) {
            $image = asset('storage/'.ltrim((string) $image, '/'));
        }

        if (! $image) {
            $image = asset($org['og_image'] ?? 'uploads/logo.png');
        }

        $title = trim((string) ($title ?? '')) ?: ($org['default_title'] ?? 'Biuro Podróży RAFA');
        $description = trim((string) ($description ?? '')) ?: ($org['default_description'] ?? '');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywordsCollection->merge($baseKeywords)->map(fn ($kw) => mb_strtolower(trim($kw)))->unique()->implode(', '),
            'image' => $image,
            'canonical' => $canonical,
            'og_type' => $context['og_type'] ?? 'website',
        ];
    }

    private static function resolveEventTemplate(array $context): ?EventTemplate
    {
        foreach (['eventTemplate', 'item', 'package'] as $key) {
            if (isset($context[$key]) && $context[$key] instanceof EventTemplate) {
                return $context[$key];
            }
        }

        return null;
    }

    private static function plainText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    }
}
