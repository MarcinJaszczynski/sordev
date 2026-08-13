<?php

declare(strict_types=1);

namespace App\Support\Help;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class HelpCatalog
{
    public const PANEL_ADMIN = 'admin';

    public const PANEL_PORTAL = 'portal';

    public const PANEL_PILOT = 'pilot';

    /** @var array<string, list<HelpArticle>>|null */
    private static ?array $cache = null;

    /**
     * @return list<HelpArticle>
     */
    public function forPanel(string $panel): array
    {
        return $this->all()[$panel] ?? [];
    }

    public function find(string $panel, string $slug): ?HelpArticle
    {
        foreach ($this->forPanel($panel) as $article) {
            if ($article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return list<HelpArticle>
     */
    public function search(string $panel, string $query): array
    {
        return array_values(array_filter(
            $this->forPanel($panel),
            fn (HelpArticle $article): bool => $article->matches($query),
        ));
    }

    /**
     * @return list<string>
     */
    public function categories(string $panel): array
    {
        $categories = [];
        foreach ($this->forPanel($panel) as $article) {
            $categories[$article->category] = true;
        }

        return array_keys($categories);
    }

    public function renderHtml(HelpArticle $article): string
    {
        return Str::markdown($article->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array<string, list<HelpArticle>>
     */
    private function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $root = resource_path('help');
        $panels = [self::PANEL_ADMIN, self::PANEL_PORTAL, self::PANEL_PILOT];
        $result = [];

        foreach ($panels as $panel) {
            $dir = $root.DIRECTORY_SEPARATOR.$panel;
            $articles = [];

            if (is_dir($dir)) {
                foreach (File::files($dir) as $file) {
                    if ($file->getExtension() !== 'md') {
                        continue;
                    }

                    $article = $this->parseFile($panel, $file->getPathname());
                    if ($article !== null) {
                        $articles[] = $article;
                    }
                }
            }

            usort($articles, fn (HelpArticle $a, HelpArticle $b): int => $a->sort <=> $b->sort
                ?: strcmp($a->title, $b->title));

            $result[$panel] = $articles;
        }

        return self::$cache = $result;
    }

    private function parseFile(string $panel, string $path): ?HelpArticle
    {
        $raw = File::get($path);
        if (! preg_match('/\A---\s*\R(.*?)\R---\s*\R?(.*)\z/s', $raw, $matches)) {
            return null;
        }

        $meta = $this->parseFrontMatter($matches[1]);
        $slug = (string) ($meta['slug'] ?? pathinfo($path, PATHINFO_FILENAME));
        $title = trim((string) ($meta['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $tags = $this->parseTags($meta['tags'] ?? '');

        return new HelpArticle(
            slug: $slug,
            panel: $panel,
            title: $title,
            summary: trim((string) ($meta['summary'] ?? '')),
            category: trim((string) ($meta['category'] ?? 'Ogólne')),
            tags: $tags,
            sort: (int) ($meta['sort'] ?? 100),
            path: $path,
            body: trim($matches[2]),
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseFrontMatter(string $block): array
    {
        $meta = [];
        foreach (preg_split('/\R/', $block) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim($value);
        }

        return $meta;
    }

    /**
     * @return list<string>
     */
    private function parseTags(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        $parts = preg_split('/\s*,\s*/', trim((string) $raw)) ?: [];

        return array_values(array_filter($parts, fn (string $tag): bool => $tag !== ''));
    }

    /** @internal testing */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
