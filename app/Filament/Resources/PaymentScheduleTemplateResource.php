<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PaymentScheduleTemplateFormFields;
use App\Filament\Resources\PaymentScheduleTemplateResource\Pages;
use App\Models\PaymentScheduleTemplate;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class PaymentScheduleTemplateResource extends Resource
{
    /** @var class-string<PaymentScheduleTemplate> */
    protected static ?string $model = PaymentScheduleTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Szablony harmonogramów';

    protected static ?string $modelLabel = 'szablon harmonogramu';

    protected static ?string $pluralModelLabel = 'szablony harmonogramów';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONFIG;

    protected static ?int $navigationSort = 6;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema(PaymentScheduleTemplateFormFields::schema());
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Transze')
                    ->counts('installments')
                    ->badge(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => 'v'.(int) $state),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
                Tables\Columns\TextColumn::make('applies_to')
                    ->label('Typy')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return 'Wszystkie';
                        }

                        return collect($state)
                            ->map(fn ($key) => PaymentScheduleTemplate::$appliesToOptions[$key] ?? $key)
                            ->implode(', ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->label('Aktualizacja')->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('newVersion')
                    ->label('Nowa wersja')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (PaymentScheduleTemplate $record): void {
                        $clone = $record->createNewVersion('Sklonowano z v'.$record->version);
                        Notification::make()
                            ->title('Utworzono wersję v'.$clone->version)
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentScheduleTemplates::route('/'),
            'create' => Pages\CreatePaymentScheduleTemplate::route('/create'),
            'edit' => Pages\EditPaymentScheduleTemplate::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        if (! isset($data['id'])) {
            return $data;
        }

        $template = PaymentScheduleTemplate::query()->with('installments')->find($data['id']);
        if (! $template) {
            return $data;
        }

        $data['installments'] = $template->installments->map(fn ($row): array => [
            'label' => $row->label,
            'share_type' => $row->share_type,
            'percent' => $row->percent !== null ? (float) $row->percent : null,
            'amount_pln' => $row->amount_pln !== null ? (float) $row->amount_pln : null,
            'amount_foreign' => $row->amount_foreign !== null ? (float) $row->amount_foreign : null,
            'currency_code' => $row->currency_code,
            'paid_by' => $row->paid_by,
            'due_offset_from_days' => $row->due_offset_from_days ?? $row->due_offset_days,
            'due_offset_to_days' => $row->due_offset_to_days ?? $row->due_offset_days,
            'due_offset_days' => $row->due_offset_days,
            'notes' => $row->notes,
        ])->all();

        return $data;
    }

    public static function afterSave(Model $record, array $data): void
    {
        if (! $record instanceof PaymentScheduleTemplate) {
            return;
        }

        $rows = is_array($data['installments'] ?? null) ? $data['installments'] : [];
        app(\App\Services\PaymentScheduleTemplateService::class)->syncInstallments($record, $rows);
    }
}
