<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Services\NotificationService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected int $pendingGratisCount = 0;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = 0;

        try {
            $variants = $this->record->qtyVariants()->get();
            if ($variants->isNotEmpty()) {
                $bestVariant = $variants
                    ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $participantCount))
                    ->first();

                $gratisCount = (int) ($bestVariant->gratis ?? 0);
            }
        } catch (\Throwable $e) {
            $gratisCount = 0;
        }

        try {
            $data['total_cost'] = $this->record->resolvedBaseTotalCost(
                $participantCount,
                $gratisCount,
                ! empty($data['start_place_id']) ? (int) $data['start_place_id'] : null
            );
        } catch (\Throwable $e) {
            // keep existing total_cost value when recalculation fails
        }

        $data['gratis_count'] = $data['gratis_count'] ?? $gratisCount;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $participantCount = max(1, (int) ($data['participant_count'] ?? 1));
        $gratisCount = max(0, (int) ($data['gratis_count'] ?? 0));
        $startPlaceId = ! empty($data['start_place_id']) ? (int) $data['start_place_id'] : null;

        $this->pendingGratisCount = $gratisCount;
        unset($data['gratis_count']);

        try {
            $data['total_cost'] = $this->record->resolvedBaseTotalCost(
                $participantCount,
                $gratisCount,
                $startPlaceId
            );
        } catch (\Throwable $e) {
            // keep existing total_cost when recalculation fails
        }

        return $data;
    }

    protected function afterSave(): void
    {
        try {
            $this->record->syncQtyVariantForGroup(
                max(1, (int) ($this->record->participant_count ?? 1)),
                $this->pendingGratisCount
            );
        } catch (\Throwable $e) {
            // ignore qty sync failures after save
        }

        try {
            $this->record->refreshActiveSettlementCosts();
        } catch (\Throwable $e) {
            // ignore settlement refresh failures silently
        }

        // Odśwież cache powiadomień dla bieżącego użytkownika
        if ($userId = Auth::id()) {
            NotificationService::clearCacheForUser($userId);
        }
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Podstawowe informacje';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('offer_word')
                ->label('Oferta DOCX')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->url(fn () => route('admin.events.offer.word', ['event' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('pdf_pilot')
                ->label('Pakiet pilota (PDF)')
                ->icon('heroicon-o-user-circle')
                ->color('success')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'pilot']))
                ->openUrlInNewTab(),

            Actions\Action::make('pdf_hotel')
                ->label('Pakiet hotelu (PDF)')
                ->icon('heroicon-o-building-office-2')
                ->color('info')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'hotel']))
                ->openUrlInNewTab(),

            Actions\Action::make('pdf_driver')
                ->label('Pakiet kierowcy (PDF)')
                ->icon('heroicon-o-map')
                ->color('warning')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'driver']))
                ->openUrlInNewTab(),

            Actions\Action::make('pdf_folder')
                ->label('Pakiet teczki (PDF)')
                ->icon('heroicon-o-folder-open')
                ->color('primary')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'folder']))
                ->openUrlInNewTab(),

            Actions\Action::make('pdf_all_packages')
                ->label('Komplet pakietów (ZIP)')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'all']))
                ->openUrlInNewTab(),
        ];
    }
}
