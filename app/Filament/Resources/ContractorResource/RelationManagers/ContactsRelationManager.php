<?php

namespace App\Filament\Resources\ContractorResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    // Use an existing DB column for search/ordering. Filament will perform SQL
    // queries against this attribute, so it must exist in the database.
    protected static ?string $recordTitleAttribute = 'first_name';

    // Build a human-friendly title for display combining first and last name.
    public static function getRecordTitle(?Model $record): string
    {
        if (! $record) {
            return '';
        }

        return trim((string) ($record->first_name ?? '') . ' ' . ($record->last_name ?? ''));
    }
    
    protected static ?string $title = 'Kontakty';
    
    protected static ?string $modelLabel = 'kontakt';
    
    protected static ?string $pluralModelLabel = 'kontakty';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label('Imię')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->label('Nazwisko')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('position')
                    ->label('Stanowisko')
                    ->maxLength(255),
                Forms\Components\TextInput::make('department')
                    ->label('Dział')
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefon')
                    ->tel(),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email(),
                Forms\Components\Textarea::make('notes')
                    ->label('Uwagi')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Imię')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Nazwisko')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Stanowisko'),
                Tables\Columns\TextColumn::make('department')
                    ->label('Dział'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
                Tables\Columns\TextColumn::make('contractors')
                    ->label('Kontrahenci')
                    ->formatStateUsing(fn ($state, $record) => isset($record) ? $record->contractors->pluck('name')->implode(', ') : null)
                    ->wrap()
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()
                    ->multiple(true)
                    ->recordSelect(function ($select) {
                        // customize the Select component's search behaviour to prefer exact full-name matches
                        $select = $select->getSearchResultsUsing(function (Select $component, string $search) {
                            $table = $this->getTable();

                            /** @var Relation $relationship */
                            $relationship = Relation::noConstraints(fn () => $table->getRelationship());

                            $relationshipQuery = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);

                            $optionsLimit = $component->getOptionsLimit();

                            // detect available search columns
                            $wanted = ['first_name', 'last_name', 'company_name', 'email', 'phone'];
                            $cols = Schema::hasTable('contacts') ? Schema::getColumnListing('contacts') : [];
                            $searchCols = array_values(array_intersect($cols, $wanted));

                            $term = trim($search);

                            $results = [];

                            // full-name exact match first
                            if ($term !== '') {
                                $connection = $relationshipQuery->getConnection();
                                $driver = $connection->getDriverName() ?? $connection->getConfig('driver');

                                if ($driver === 'sqlite') {
                                    $fullExpr = "contacts.first_name || ' ' || contacts.last_name";
                                } else {
                                    // mysql, pgsql
                                    $fullExpr = "CONCAT(contacts.first_name, ' ', contacts.last_name)";
                                }

                                try {
                                    $exactQuery = (clone $relationshipQuery)
                                        ->with('contractors')
                                        ->whereRaw("{$fullExpr} = ?", [$term])
                                        ->limit($optionsLimit)
                                        ->get();

                                    foreach ($exactQuery as $r) {
                                        $contractors = $r->contractors->pluck('name')->implode(', ');
                                        $meta = trim(implode(' • ', array_filter([
                                            $r->position ? "Stanowisko: {$r->position}" : null,
                                            $r->department ? "Dział: {$r->department}" : null,
                                            $r->phone ? "Tel: {$r->phone}" : null,
                                            $r->email ? "Email: {$r->email}" : null,
                                            $contractors ? "Kontrahenci: {$contractors}" : null,
                                        ])));

                                        $label = $this->getRecordTitle($r);
                                        $results[$r->getKey()] = $meta ? ("{$label} — {$meta}") : $label;
                                    }
                                } catch (\Exception $e) {
                                    // ignore DB errors from fullExpr and continue with partial search
                                }
                            }

                            // if we already have enough results, return
                            if (count($results) >= $optionsLimit) {
                                return array_slice($results, 0, $optionsLimit, true);
                            }

                            // partial matches across allowed columns
                            if (count($searchCols)) {
                                $partialQuery = (clone $relationshipQuery);

                                if ($term !== '') {
                                    $partialQuery->where(function (Builder $q) use ($searchCols, $term) {
                                        $first = true;
                                        foreach ($searchCols as $col) {
                                            $method = $first ? 'where' : 'orWhere';
                                            $q->{$method}("contacts.{$col}", 'like', "%{$term}%");
                                            $first = false;
                                        }
                                    });
                                }

                                if (count($results)) {
                                    $partialQuery->whereNotIn($relationship->getRelated()->getKeyName(), array_keys($results));
                                }

                                $remaining = $optionsLimit - count($results);

                                $partial = $partialQuery->with('contractors')->limit($remaining)->get();

                                foreach ($partial as $r) {
                                    $contractors = $r->contractors->pluck('name')->implode(', ');
                                    $meta = trim(implode(' • ', array_filter([
                                        $r->position ? "Stanowisko: {$r->position}" : null,
                                        $r->department ? "Dział: {$r->department}" : null,
                                        $r->phone ? "Tel: {$r->phone}" : null,
                                        $r->email ? "Email: {$r->email}" : null,
                                        $contractors ? "Kontrahenci: {$contractors}" : null,
                                    ])));

                                    $label = $this->getRecordTitle($r);
                                    $results[$r->getKey()] = $meta ? ("{$label} — {$meta}") : $label;
                                }
                            }

                            return $results;
                        })
                        ->multiple(true)
                        ->helperText('Zaznacz/odznacz wszystkie pozycje dla operacji zbiorczych.');

                        return $select;
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}