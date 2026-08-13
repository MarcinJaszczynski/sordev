<?php

declare(strict_types=1);

namespace App\Support\Help;

readonly class HelpArticle
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $slug,
        public string $panel,
        public string $title,
        public string $summary,
        public string $category,
        public array $tags,
        public int $sort,
        public string $path,
        public string $body,
    ) {}

    public function matches(string $query): bool
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', [
            $this->title,
            $this->summary,
            $this->category,
            implode(' ', $this->tags),
            $this->body,
        ]));

        return str_contains($haystack, $needle);
    }
}
