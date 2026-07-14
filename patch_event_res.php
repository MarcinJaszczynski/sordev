<?php
$content = file_get_contents('app/Filament/Resources/EventResource.php');

$newFields = <<<'PHP'
                Forms\Components\Select::make('start_place_id')
                    ->label('Miejsce wyjazdu (podstawienia)')
                    ->options(\App\Models\Place::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, callable $set): void {
                        $templateId = (int) ($get('event_template_id') ?? 0);
                        $startPlaceId = (int) ($get('start_place_id') ?? 0);
                        $currentTransfer = (float) ($get('transfer_km') ?? 0);

                        if ($templateId) {
                            $set('transfer_km', static::resolveTransferKmFromTemplateState(
                                $templateId,
                                $startPlaceId,
                                $currentTransfer
                            ));
                        } else {
                            $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                            if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                                $d1 = (float) (\App\Models\PlaceDistance::query()
                                    ->where('from_place_id', $startPlaceId)
                                    ->where('to_place_id', $programStartPlaceId)
                                    ->value('distance_km') ?? 0);
                                $set('transfer_km', $d1 * 2);
                            }
                        }

                        static::refreshTotalCostFromTemplateState($set, $get);
                    }),

                Forms\Components\Select::make('program_start_place_id')
                    ->label('Początek programu')
                    ->options(\App\Models\Place::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->dehydrated(false)
                    ->reactive()
                    ->visible(fn (callable $get) => empty($get('event_template_id')))
                    ->afterStateUpdated(function (callable $get, callable $set): void {
                        $startPlaceId = (int) ($get('start_place_id') ?? 0);
                        $programStartPlaceId = (int) ($get('program_start_place_id') ?? 0);
                        if ($programStartPlaceId > 0 && $startPlaceId > 0) {
                            $d1 = (float) (\App\Models\PlaceDistance::query()
                                ->where('from_place_id', $startPlaceId)
                                ->where('to_place_id', $programStartPlaceId)
                                ->value('distance_km') ?? 0);
                            $set('transfer_km', $d1 * 2);
                        }
                    })
                    ->helperText('Służy tylko do przeliczenia transferu (x2).'),

                Forms\Components\TextInput::make('transfer_km')
                    ->label('Km transferu')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($livewire) => method_exists($livewire, 'dispatch') ? $livewire->dispatch('event-price-table-refresh') : null),

                Forms\Components\TextInput::make('program_km')
                    ->label('Km programu')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($livewire, callable $get, callable $set) => [
                        static::refreshTotalCostFromTemplateState($set, $get),
                        method_exists($livewire, 'dispatch') ? $livewire->dispatch('event-price-table-refresh') : null
                    ]),
PHP;

$content = preg_replace("/\.\.\.EventTransportFields::manualTransportCostFields\(\),/s", $newFields . "\n\n                ...EventTransportFields::manualTransportCostFields(),", $content);

file_put_contents('app/Filament/Resources/EventResource.php', $content);
echo "Added back to EventResource.\n";
