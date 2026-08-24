<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Models\EventTemplate;
use App\Models\FaqEntry;
use App\Models\SeoSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SchemaBuilder
{
    public static function siteUrl(): string
    {
        return rtrim((string) (config('app.public_url') ?: config('app.url') ?: request()->getSchemeAndHttpHost()), '/');
    }

    public static function organizationId(): string
    {
        return self::siteUrl().'/#organization';
    }

    public static function organization(array $overrides = []): array
    {
        $org = array_merge(SeoSetting::organization(), $overrides);
        $siteUrl = self::siteUrl();
        $logo = self::absoluteUrl($org['og_image'] ?? 'uploads/logo.png');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::organizationId(),
            'name' => $org['name'],
            'legalName' => $org['legal_name'] ?? $org['name'],
            'url' => $siteUrl,
            'logo' => $logo,
            'description' => $org['default_description'] ?? null,
            'telephone' => $org['phone'] ?? null,
            'email' => $org['email'] ?? null,
            'sameAs' => array_values(array_filter([
                $org['facebook'] ?? null,
                $org['instagram'] ?? null,
                $siteUrl,
            ])),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $org['street'] ?? null,
                'addressLocality' => $org['city'] ?? null,
                'postalCode' => $org['postal_code'] ?? null,
                'addressCountry' => $org['country'] ?? 'PL',
            ],
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'Polska'],
            ],
        ];
    }

    public static function travelAgency(array $overrides = []): array
    {
        $schema = self::organization($overrides);
        $schema['@type'] = 'TravelAgency';
        $schema['openingHoursSpecification'] = [
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'opens' => '10:00',
                'closes' => '16:00',
            ],
        ];

        return $schema;
    }

    public static function webSite(): array
    {
        $siteUrl = self::siteUrl();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $siteUrl.'/#website',
            'url' => $siteUrl,
            'name' => SeoSetting::organization()['name'],
            'publisher' => ['@id' => self::organizationId()],
        ];
    }

    public static function aboutPage(string $title, string $description, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'isPartOf' => ['@id' => self::siteUrl().'/#website'],
            'about' => ['@id' => self::organizationId()],
        ];
    }

    public static function faqPage(Collection $faqs): ?array
    {
        $items = $faqs
            ->filter(fn (FaqEntry $faq) => $faq->include_in_schema)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn (FaqEntry $faq) => [
                '@type' => 'Question',
                'name' => strip_tags($faq->question),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq->answer),
                ],
            ])->all(),
        ];
    }

    public static function breadcrumbList(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(function ($item, $index) {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'] ?? null,
                ];
            })->all(),
        ];
    }

    public static function touristTrip(EventTemplate $template, ?string $canonicalUrl = null, ?float $price = null): array
    {
        $description = self::plainText(
            $template->seo_description
            ?: Str::limit(strip_tags((string) $template->event_description), 500)
        );

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $template->seo_title ?: $template->name,
            'description' => $description,
            'url' => $canonicalUrl,
            'provider' => ['@id' => self::organizationId()],
            'touristType' => ['School groups', 'Educational groups', 'Corporate groups'],
        ];

        if ($template->duration_days) {
            $schema['itinerary'] = [
                '@type' => 'ItemList',
                'numberOfItems' => (int) $template->duration_days,
                'name' => 'Program '.$template->duration_days.'-dniowej wycieczki',
            ];
        }

        if ($price !== null && $price > 0) {
            $schema['offers'] = self::offer($template, $price, $canonicalUrl);
        }

        $image = $template->full_image_url;
        if ($image) {
            $schema['image'] = $image;
        }

        return array_filter($schema);
    }

    public static function product(EventTemplate $template, ?float $price = null, ?string $canonicalUrl = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $template->seo_title ?: $template->name,
            'description' => self::plainText($template->seo_description ?: Str::limit(strip_tags((string) $template->event_description), 500)),
            'url' => $canonicalUrl,
            'brand' => ['@type' => 'Brand', 'name' => SeoSetting::organization()['name']],
            'category' => 'Wycieczki szkolne',
        ];

        $image = $template->full_image_url;
        if ($image) {
            $schema['image'] = $image;
        }

        if ($price !== null && $price > 0) {
            $schema['offers'] = self::offer($template, $price, $canonicalUrl);
        }

        return array_filter($schema);
    }

    public static function article(BlogPost $post, string $url): array
    {
        $image = $post->featured_image
            ? self::absoluteUrl('storage/'.ltrim($post->featured_image, '/'))
            : null;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'url' => $url,
            'datePublished' => optional($post->published_at ?? $post->created_at)?->toAtomString(),
            'dateModified' => optional($post->updated_at)?->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => SeoSetting::organization()['name'],
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => SeoSetting::organization()['name'],
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => self::absoluteUrl(SeoSetting::organization()['og_image'] ?? 'uploads/logo.png'),
                ],
            ],
            'image' => $image,
        ]);
    }

    private static function offer(EventTemplate $template, float $price, ?string $canonicalUrl): array
    {
        return [
            '@type' => 'Offer',
            'price' => number_format($price, 2, '.', ''),
            'priceCurrency' => 'PLN',
            'availability' => 'https://schema.org/InStock',
            'url' => $canonicalUrl,
            'seller' => ['@id' => self::organizationId()],
            'name' => $template->name,
        ];
    }

    private static function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return self::siteUrl().'/'.ltrim($path, '/');
    }

    private static function plainText(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    }
}
