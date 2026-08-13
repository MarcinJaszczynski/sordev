<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MailTemplateResource\Pages;
use App\Models\MailTemplate;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MailTemplateResource extends Resource
{
    protected static ?string $model = MailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Szablony maili';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?string $modelLabel = 'Szablon maila';

    protected static ?string $pluralModelLabel = 'Szablony maili';

    protected static ?int $navigationSort = 40;

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('mail_templates') && static::canManage();
    }

    protected static function canManage(): bool
    {
        return (bool) Auth::user()?->hasRole(['admin', 'super_admin']);
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label('Klucz')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(100)
                ->helperText('np. payment_reminder, event_status_changed'),
            Forms\Components\TextInput::make('name')
                ->label('Nazwa')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('subject')
                ->label('Temat')
                ->required()
                ->maxLength(255)
                ->helperText('Placeholdery: {{ event_code }}, {{ recipient_name }}, …'),
            Forms\Components\Textarea::make('body_html')
                ->label('Treść HTML / Blade')
                ->rows(12)
                ->required()
                ->columnSpanFull(),
            Forms\Components\TagsInput::make('placeholders')
                ->label('Placeholdery (dokumentacja)')
                ->placeholder('Wpisz klucz placeholdera')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktywny')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Klucz')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable(),
                Tables\Columns\TextColumn::make('subject')->label('Temat')->limit(40),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Aktualizacja')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailTemplates::route('/'),
            'create' => Pages\CreateMailTemplate::route('/create'),
            'edit' => Pages\EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
