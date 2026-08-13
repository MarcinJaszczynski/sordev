<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentSectionResource\Pages;
use App\Models\DocumentSection;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DocumentSectionResource extends Resource
{
    protected static ?string $model = DocumentSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Sekcje dokumentów CMS';

    protected static ?string $modelLabel = 'sekcja dokumentów';

    protected static ?string $pluralModelLabel = 'sekcje dokumentów';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Tytuł')->required(),
            Forms\Components\TextInput::make('slug')
                ->label('Identyfikator URL')
                ->helperText('Fragment adresu sekcji w URL.')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('order_number')->label('Kolejność')->default(0)->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('slug')->label('Identyfikator URL'),
            TextColumn::make('order_number')->label('Kolejność')->sortable(),
        ])->defaultSort('order_number');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentSections::route('/'),
            'create' => Pages\CreateDocumentSection::route('/create'),
            'edit' => Pages\EditDocumentSection::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user instanceof \App\Models\User) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('create document section');
    }
}
