<?php

use App\Support\TiptapContentNormalizer;

test('numeric json scalar is wrapped as html paragraph', function (): void {
    expect(TiptapContentNormalizer::normalize('570'))->toBe('<p>570</p>');
});

test('plain html and tiptap json documents stay untouched', function (): void {
    expect(TiptapContentNormalizer::normalize('Chianciano Terme'))->toBe('Chianciano Terme');
    expect(TiptapContentNormalizer::normalize('<p>uwagi</p>'))->toBe('<p>uwagi</p>');

    $document = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"ok"}]}]}';
    expect(TiptapContentNormalizer::normalize($document))->toBe($document);
});
