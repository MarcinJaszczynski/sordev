<?php

namespace App\Filament\Forms;

use App\Models\ContractTemplate;
use App\Services\AgreementPlaceholderCatalog;
use App\Services\AgreementTemplateRenderer;
use App\Support\AgreementHtml;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

/**
 * Formularz szablonu umowy: RichEditor (bold itd.), znaczniki, pola własne, podgląd.
 */
final class ContractTemplateFormFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Section::make('Podstawowe')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa szablonu')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktywny')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Nieaktywne wersje nie pojawiają się przy tworzeniu umów.')
                        ->columnSpan(1),

                    Forms\Components\CheckboxList::make('applies_to')
                        ->label('Dotyczy typów umów')
                        ->options(ContractTemplate::$appliesToOptions)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->helperText('Puste = uniwersalny szablon (wszystkie typy).')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('version')
                        ->label('Wersja')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->default(1),

                    Forms\Components\Textarea::make('version_notes')
                        ->label('Notatka wersji')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Treść szablonu')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('insert_placeholder')
                                ->label('Wstaw znacznik systemowy')
                                ->options(fn (): array => app(AgreementPlaceholderCatalog::class)->optionsForSelect())
                                ->searchable()
                                ->nullable()
                                ->dehydrated(false)
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                    if (! is_string($state) || $state === '') {
                                        return;
                                    }

                                    static::appendPlaceholderToContent($set, $get, $state);
                                    $set('insert_placeholder', null);
                                })
                                ->helperText('Dopisuje znacznik na końcu treści.'),

                            Forms\Components\Select::make('insert_custom_placeholder')
                                ->label('Wstaw własne pole')
                                ->options(function (Get $get): array {
                                    $options = [];
                                    foreach ((array) ($get('custom_placeholders') ?? []) as $row) {
                                        if (! is_array($row)) {
                                            continue;
                                        }
                                        $key = trim((string) ($row['key'] ?? ''), '[]');
                                        if ($key === '') {
                                            continue;
                                        }
                                        $label = filled($row['label'] ?? null) ? (string) $row['label'] : $key;
                                        $tag = '['.$key.']';
                                        $options[$tag] = $label.' '.$tag;
                                    }

                                    return $options;
                                })
                                ->searchable()
                                ->nullable()
                                ->dehydrated(false)
                                ->live()
                                ->visible(fn (Get $get): bool => filled($get('custom_placeholders')))
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                    if (! is_string($state) || $state === '') {
                                        return;
                                    }

                                    static::appendPlaceholderToContent($set, $get, $state);
                                    $set('insert_custom_placeholder', null);
                                }),
                        ]),

                    Forms\Components\RichEditor::make('content')
                        ->label('Treść')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'h2',
                            'h3',
                            'bulletList',
                            'orderedList',
                            'blockquote',
                            'redo',
                            'undo',
                        ])
                        ->extraInputAttributes(['class' => 'contract-template-rich'])
                        ->columnSpanFull()
                        ->required()
                        ->helperText('Bold / listy / nagłówki. Znaczniki: [NAZWA_IMPREZY], [godzina] itd.'),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('preview_template')
                            ->label('Podgląd z przykładowymi danymi')
                            ->icon('heroicon-o-eye')
                            ->color('gray')
                            ->modalHeading('Podgląd szablonu')
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Zamknij')
                            ->modalContent(function (Get $get): HtmlString {
                                $content = AgreementHtml::normalizeContent($get('content'));

                                $template = new ContractTemplate([
                                    'content' => $content,
                                    'custom_placeholders' => $get('custom_placeholders') ?? [],
                                ]);

                                $catalog = app(AgreementPlaceholderCatalog::class);
                                $payload = $catalog->samplePayload();
                                $payload['custom_placeholder_values'] = collect($template->normalizedCustomPlaceholders())
                                    ->mapWithKeys(fn (array $def): array => [
                                        $def['key'] => $def['default'] ?: ('[przykład: '.$def['key'].']'),
                                    ])
                                    ->all();

                                $rendered = app(AgreementTemplateRenderer::class)->render($template, $payload);
                                $unresolved = app(AgreementTemplateRenderer::class)->unresolvedPlaceholders(
                                    strip_tags(AgreementHtml::normalizeContent($rendered))
                                );
                                $warning = $unresolved !== []
                                    ? '<p style="color:#b45309;margin-bottom:12px;"><strong>Nierozwiązane znaczniki:</strong> '
                                        .e(implode(', ', $unresolved))
                                        .'</p>'
                                    : '';

                                return new HtmlString(
                                    '<div class="contract-template-preview">'
                                    .$warning
                                    .AgreementHtml::toPreviewHtml($rendered)
                                    .'</div>'
                                );
                            }),
                    ])->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Pola własne')
                ->description('Np. klucz „godzina” → w treści [godzina]. Przy umowie pojawi się pole do uzupełnienia.')
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('custom_placeholders')
                        ->label('Definicje pól')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->label('Klucz (bez nawiasów)')
                                ->required()
                                ->maxLength(64)
                                ->helperText('Np. godzina')
                                ->rule('regex:/^[A-Za-z0-9_\-]+$/u'),
                            Forms\Components\TextInput::make('label')
                                ->label('Etykieta')
                                ->required()
                                ->maxLength(120),
                            Forms\Components\TextInput::make('default')
                                ->label('Domyślna wartość')
                                ->maxLength(255),
                        ])
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => filled($state['key'] ?? null)
                            ? '['.$state['key'].'] — '.($state['label'] ?? '')
                            : null),
                ]),

            Forms\Components\Section::make('Załączniki')
                ->collapsed()
                ->schema([
                    Forms\Components\CheckboxList::make('default_attachments')
                        ->label('Domyślne załączniki dla tego szablonu')
                        ->options(fn (): array => app(\App\Services\ContractAttachmentCatalogService::class)->getOptions())
                        ->columns(1)
                        ->helperText('Puste = ustawienia globalne.'),
                ]),
        ];
    }

    private static function appendPlaceholderToContent(Set $set, Get $get, string $tag): void
    {
        $content = AgreementHtml::normalizeContent($get('content'));

        if ($content === '' || AgreementHtml::looksLikeHtml($content)) {
            $set('content', rtrim($content).'<p>'.e($tag).'</p>');

            return;
        }

        $separator = str_ends_with($content, "\n") ? '' : "\n";
        $set('content', AgreementHtml::plainTextToEditorHtml($content.$separator.$tag));
    }
}
