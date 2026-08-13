<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Support\AgreementHtml;

class AgreementTemplateRenderer
{
    public function __construct(
        private readonly AgreementPlaceholderCatalog $catalog,
    ) {}

    public function render(?ContractTemplate $template, array $payload): string
    {
        $content = AgreementHtml::normalizeContent($template?->content ?? '');
        $content = trim($content);

        if ($content === '') {
            $content = $this->defaultTemplate();
        }

        $replacements = $this->catalog->replacementsFromPayload($payload);
        $replacements = array_merge(
            $replacements,
            $this->customPlaceholderReplacements($template, $payload),
        );

        // Backward compatibility: support legacy moustache placeholders.
        foreach ($payload as $key => $value) {
            if (! is_string($key) || $key === 'custom_placeholder_values') {
                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $replacements['{{'.$key.'}}'] = (string) ($value ?? '—');
        }

        return strtr($content, $replacements);
    }

    /**
     * Wykrywa nierozwiązane znaczniki [TAG] w treści (do ostrzeżeń w podglądzie).
     *
     * @return list<string>
     */
    public function unresolvedPlaceholders(string $renderedContent): array
    {
        preg_match_all('/\[[^\[\]]+\]/u', $renderedContent, $matches);

        $found = $matches[0] ?? [];

        return array_values(array_unique($found));
    }

    /**
     * @return array<string, string>
     */
    protected function customPlaceholderReplacements(?ContractTemplate $template, array $payload): array
    {
        $definitions = $template?->normalizedCustomPlaceholders() ?? [];
        $values = (array) ($payload['custom_placeholder_values'] ?? []);
        $replacements = [];

        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $raw = $values[$key] ?? $definition['default'] ?? '—';
            $value = filled($raw) ? (string) $raw : '—';

            $replacements['['.$key.']'] = $value;
            $replacements['['.mb_strtoupper($key).']'] = $value;
            $replacements['['.mb_strtolower($key).']'] = $value;
        }

        // Wartości podane przy umowie, nawet bez definicji w szablonie (elastyczność).
        foreach ($values as $key => $raw) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = filled($raw) ? (string) $raw : '—';
            $replacements['['.$key.']'] ??= $value;
            $replacements['['.mb_strtoupper($key).']'] ??= $value;
            $replacements['['.mb_strtolower($key).']'] ??= $value;
        }

        return $replacements;
    }

    protected function defaultTemplate(): string
    {
        return implode("\n", [
            'UMOWA [NUMER_UMOWY]',
            'Data: [DATA_UMOWY]',
            'Typ: [TYP_UMOWY]',
            '',
            'Impreza: [NAZWA_IMPREZY]',
            'Termin: [DATA_START] - [DATA_KONIEC]',
            'Liczba uczestników: [LICZBA_OSOB]',
            'Uczestnik: [UCZESTNIK]',
            'Data urodzenia uczestnika: [DATA_URODZENIA]',
            'Referencja: [REFERENCJA_REZERWACJI]',
            '',
            'Klient: [KLIENT]',
            'Email: [EMAIL]',
            'Telefon: [TELEFON]',
            '',
            'Kwota do zapłaty: [KWOTA] [WALUTA]',
            '',
            'Link do zawarcia i opłacenia umowy:',
            '[LINK_UMOWY]',
        ]);
    }
}
