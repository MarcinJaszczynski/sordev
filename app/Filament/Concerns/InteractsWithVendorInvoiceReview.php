<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\VendorInvoiceResource;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\VendorInvoice;
use App\Services\Invoices\VendorInvoiceProgramPointSync;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;

trait InteractsWithVendorInvoiceReview
{
    /**
     * @return array<int, Action>
     */
    protected function vendorInvoiceReviewActions(bool $includeEdit = true): array
    {
        $actions = [
            $this->vendorInvoicePreviewAction(),
            $this->vendorInvoiceAssignAction(),
            Tables\Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document')
                ->url(fn (VendorInvoice $record) => $record->pdf_url)
                ->openUrlInNewTab()
                ->visible(fn (VendorInvoice $record) => (bool) $record->pdf_path),
        ];

        if ($includeEdit) {
            $actions[] = Tables\Actions\Action::make('edit')
                ->label('Edytuj')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (VendorInvoice $record) => VendorInvoiceResource::getUrl('edit', ['record' => $record]));
        }

        return $actions;
    }

    protected function vendorInvoicePreviewAction(): Action
    {
        return Tables\Actions\Action::make('preview')
            ->label('Podgląd')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (VendorInvoice $record) => 'Faktura '.$record->invoice_number)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zamknij')
            ->modalWidth('5xl')
            ->modalContent(function (VendorInvoice $record) {
                $record->loadMissing([
                    'lines',
                    'contractor',
                    'event',
                    'programPoint.contractor',
                    'programPoints.contractor',
                    'importBatch',
                ]);

                return view('filament.pages.partials.vendor-invoice-preview', [
                    'invoice' => $record,
                ]);
            });
    }

    protected function vendorInvoiceAssignAction(): Action
    {
        return Tables\Actions\Action::make('assign')
            ->label('Przypisz')
            ->icon('heroicon-o-link')
            ->form([
                Select::make('event_id')
                    ->label('Impreza')
                    ->searchable()
                    ->required()
                    ->live()
                    ->default(fn (VendorInvoice $record) => $record->event_id)
                    ->afterStateUpdated(fn (callable $set) => $set('event_program_point_ids', []))
                    ->getSearchResultsUsing(fn (string $search) => Event::query()
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Event $e) => [$e->id => "{$e->code} — {$e->name}"]))
                    ->getOptionLabelUsing(fn ($value) => optional(Event::find($value), fn (Event $e) => "{$e->code} — {$e->name}")),
                Select::make('event_program_point_ids')
                    ->label('Punkty programu')
                    ->multiple()
                    ->searchable()
                    ->helperText('Faktura może obejmować kilka punktów (np. nocleg, obiad, śniadanie).')
                    ->options(fn (callable $get) => $get('event_id')
                        ? EventProgramPoint::query()
                            ->where('event_id', $get('event_id'))
                            ->with('contractor')
                            ->orderBy('day')
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (EventProgramPoint $p) => [
                                $p->id => ($p->contractor?->name ?? $p->name ?? 'Punkt').' (dzień '.$p->day.')',
                            ])
                        : []),
                Select::make('contractor_id')
                    ->label('Kontrahent')
                    ->searchable()
                    ->default(fn (VendorInvoice $record) => $record->contractor_id)
                    ->getSearchResultsUsing(fn (string $search) => Contractor::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value) => Contractor::find($value)?->name),
            ])
            ->fillForm(function (VendorInvoice $record) {
                $record->loadMissing('programPoints');

                $pointIds = $record->programPoints->pluck('id')->all();
                if ($pointIds === [] && $record->event_program_point_id) {
                    $pointIds = [(int) $record->event_program_point_id];
                }

                return [
                    'event_id' => $record->event_id,
                    'event_program_point_ids' => $pointIds,
                    'contractor_id' => $record->contractor_id,
                ];
            })
            ->action(function (VendorInvoice $record, array $data) {
                $pointIds = collect($data['event_program_point_ids'] ?? [])
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $record->update([
                    'event_id' => $data['event_id'],
                    'event_program_point_id' => $pointIds[0] ?? null,
                    'contractor_id' => $data['contractor_id'] ?? $record->contractor_id,
                    'matching_status' => 'manual',
                ]);

                $record->syncProgramPointLinks($pointIds);

                // Wariant A: sync kwoty tylko przy dokładnie jednym punkcie — bez auto-podziału.
                if (count($pointIds) === 1) {
                    app(VendorInvoiceProgramPointSync::class)->sync($record->fresh());
                }

                Notification::make()->title('Faktura przypisana')->success()->send();
            });
    }
}
