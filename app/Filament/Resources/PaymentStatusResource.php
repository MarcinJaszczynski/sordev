<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentStatusResource\Pages;
use App\Models\PaymentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource Filament dla modelu PaymentStatus.
 * Definiuje formularz, tabelę, uprawnienia i strony powiązane ze statusami płatności.
 */
class PaymentStatusResource extends Resource
{
    /**
     * Powiązany model Eloquent
     *
     * @var class-string<PaymentStatus>
     */
    protected static ?string $model = PaymentStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Statusy płatności';

    protected static ?string $navigationGroup = 'Ustawienia kalkulacji';

    protected static ?string $modelLabel = 'Status płatności';

    protected static ?string $pluralModelLabel = 'Statusy płatności';

    /**
     * Uprawnienia do widoczności resource w panelu
     */
    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view payment_status')) {
            return true;
        }

        return false;
    }

    /**
     * Uprawnienia do tworzenia, edycji i usuwania
     */
    public static function canCreate(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'create payment_status'))) {
            return true;
        }

        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'edit payment_status'))) {
            return true;
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && ($user->roles->contains('name', 'admin') || $user->roles->flatMap->permissions->contains('name', 'delete payment_status'))) {
            return true;
        }

        return false;
    }

    /**
     * Definicja formularza do edycji/dodawania statusu płatności
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nazwa statusu')
                ->required()
                ->unique(ignoreRecord: true),
        ]);
    }

    /**
     * Definicja tabeli statusów płatności w panelu
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Utworzono')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * Rejestracja stron powiązanych z tym resource (zgodnie z Filament 3)
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentStatuses::route('/'),
            'edit' => Pages\EditPaymentStatus::route('/{record}/edit'),
        ];
    }
}
