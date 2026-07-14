<?php
$content = file_get_contents('app/Filament/Forms/EventKeyInfoFields.php');

$newFields = <<<'PHP'
                    Forms\Components\Select::make('start_place_id')
                        ->label('Miejsce startu (podstawienia)')
                        ->options(\App\Models\Place::pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set): void {
                            $templateId = (int) ($get('event_template_id') ?? 0);
                            $startPlaceId = (int) ($get('start_place_id') ?? 0);
                            $currentTransfer = (float) ($get('transfer_km') ?? 0);

                            if (class_exists(\App\Filament\Resources\EventResource::class)) {
                                $set('transfer_km', \App\Filament\Resources\EventResource::resolveTransferKmFromTemplateState(
                                    $templateId,
                                    $startPlaceId,
                                    $currentTransfer
                                ));
                                \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get);
                            }
                        })
                        ->helperText('Wymagane do obliczenia transferu i ceny z szablonu.'),

                    Forms\Components\TextInput::make('program_km')
                        ->label('Kilometry programu')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (callable $get, callable $set, $livewire): void {
                            if (class_exists(\App\Filament\Resources\EventResource::class)) {
                                \App\Filament\Resources\EventResource::refreshTotalCostFromTemplateState($set, $get);
                            }
                            if (method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('event-price-table-refresh');
                            }
                        }),

                    Forms\Components\TextInput::make('transfer_km')
                        ->label('Kilometry transferu')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (callable $get, callable $set, $livewire): void {
                            if (method_exists($livewire, 'dispatch')) {
                                $livewire->dispatch('event-price-table-refresh');
                            }
                        }),
PHP;

$content = preg_replace("/Forms\\\\Components\\\\TextInput::make\('gratis_count'\).*?->helperText\('Pole pomocnicze do kalkulacji \(nie jest zapisywane w bazie\)\.'\),/s", "$0\n\n$newFields", $content);

file_put_contents('app/Filament/Forms/EventKeyInfoFields.php', $content);
echo "Fields added to EventKeyInfoFields.\n";
