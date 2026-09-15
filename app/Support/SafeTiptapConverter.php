<?php

namespace App\Support;

use FilamentTiptapEditor\TiptapConverter;

final class SafeTiptapConverter extends TiptapConverter
{
    public function asHTML(string|array|null $content, bool $toc = false, int $maxDepth = 3): string
    {
        return parent::asHTML(TiptapContentNormalizer::normalize($content), $toc, $maxDepth);
    }

    public function asJSON(string|array|null $content, bool $decoded = false, bool $toc = false, int $maxDepth = 3): string|array
    {
        return parent::asJSON(TiptapContentNormalizer::normalize($content), $decoded, $toc, $maxDepth);
    }

    public function asText(string|array|null $content): string
    {
        return parent::asText(TiptapContentNormalizer::normalize($content));
    }

    public function asTOC(string|array|null $content, int $maxDepth = 3, bool $array = false): string|array
    {
        return parent::asTOC(TiptapContentNormalizer::normalize($content), $maxDepth, $array);
    }
}
