<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlementDocument;
use App\Models\HotelRoom;
use App\Support\EventParticipantGroupLabels;
use App\Support\StoragePath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Wspólny payload danych dla klasycznych pakietów PDF (pilot / hotel / driver / folder).
 */
final class EventPrintPdfDataFactory
{
    public const AUDIENCE_LABELS = [
        'pilot' => 'Pakiet dla pilota',
        'hotel' => 'Pakiet dla hotelu',
        'driver' => 'Pakiet dla kierowcy',
        'folder' => 'Teczka imprezy',
        'all' => 'Komplet pakietów',
        'program_with_times' => 'Program imprezy (z godzinami)',
        'program_without_times' => 'Program imprezy (bez godzin)',
        'hotel_agenda' => 'Agenda dla hotelu',
        'hotel_agendas' => 'Agendy hotelowe (ZIP)',
    ];

    /**
     * @return array<string, mixed>
     */
    public function make(Event $event, string $audience): array
    {
        $company = config('company', []);
        $logoDataUri = $this->resolveLogoDataUri((string) ($company['logo_path'] ?? 'uploads/logo.png'));

        $participantCount = (int) ($event->participant_count ?? 0);
        $qtyVariant = $event->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [max(1, $participantCount)])
            ->first();

        $staffCount = (int) (optional($qtyVariant)->staff ?? 0);
        $driverCount = max(1, (int) (optional($qtyVariant)->driver ?? 1));
        $gratisCount = (int) (optional($qtyVariant)->gratis ?? 0);

        $programByDay = $this->buildProgramByDay($event);

        $pilotSetFinanceCards = [];
        if (in_array($audience, ['pilot', 'folder'], true)) {
            $pilotSetFinanceCards = array_values(
                app(PilotSetFinanceDisplay::class)->cardsForEvent($event)
            );
        }

        $hotelPlanService = app(EventHotelPlanService::class);
        $usesEventHotelPlan = $event->hotelStays()->exists();

        if ($usesEventHotelPlan) {
            $hotelPlan = $hotelPlanService->buildHotelPlanForPdf($event);
        } else {
            $hotelDays = collect($event->eventTemplate?->hotelDays ?? [])->sortBy('day')->values();
            $allRoomIds = $hotelDays
                ->flatMap(fn ($day) => $day->getAllAssignedRoomIds())
                ->filter()
                ->unique()
                ->values();

            $roomsById = HotelRoom::query()
                ->whereIn('id', $allRoomIds)
                ->get(['id', 'name', 'people_count'])
                ->keyBy('id');

            $hotelPlan = $hotelDays->map(function ($day) use ($roomsById) {
                $resolve = function (?array $ids) use ($roomsById) {
                    return collect($ids ?? [])
                        ->map(function ($id) use ($roomsById) {
                            $room = $roomsById->get((int) $id);

                            return [
                                'id' => (int) $id,
                                'name' => $room?->name ?? ('Pokój #'.$id),
                                'people_count' => $room?->people_count,
                            ];
                        })
                        ->values();
                };

                return [
                    'day' => (int) ($day->day ?? 1),
                    'qty' => $resolve($day->hotel_room_ids_qty),
                    'gratis' => $resolve($day->hotel_room_ids_gratis),
                    'staff' => $resolve($day->hotel_room_ids_staff),
                    'driver' => $resolve($day->hotel_room_ids_driver),
                    'notes' => $day->notes,
                    'uses_event_plan' => false,
                ];
            });
        }

        $individualAgreementReport = $event->buildIndividualAgreementReport($event->agreements);
        $attachmentFlag = $this->attachmentFlagForAudience($audience);
        $selectedDocuments = collect($event->activeSettlement?->documents ?? [])
            ->filter(fn ($document) => (bool) ($document->{$attachmentFlag} ?? false))
            ->filter(fn ($document) => ($document->approval_status ?? 'pending') !== 'rejected')
            ->values();

        $attachedFiles = $selectedDocuments
            ->flatMap(function ($document) {
                $docLabel = $document->document_number ?: ('Dokument #'.$document->id);

                return collect($document->files ?? [])->map(function ($relativePath) use ($docLabel, $document) {
                    $resolved = $this->resolveStoredFile((string) $relativePath);

                    if (! $resolved) {
                        return null;
                    }

                    return [
                        'document_id' => $document->id,
                        'document_label' => $docLabel,
                        'document_type' => EventSettlementDocument::$documentTypes[$document->document_type] ?? $document->document_type,
                        'relative_path' => $relativePath,
                        'absolute_path' => $resolved['absolute_path'],
                        'base_name' => $resolved['base_name'],
                        'zip_name' => $docLabel.'/'.$resolved['base_name'],
                    ];
                });
            })
            ->filter()
            ->values();

        $eventDocumentsAttached = $event->documents
            ->filter(fn ($doc) => (bool) ($doc->{$attachmentFlag} ?? false) && $doc->file_path)
            ->filter(fn ($doc) => ($doc->approval_status ?? 'pending') !== 'rejected')
            ->map(function ($doc) {
                $resolved = $this->resolveStoredFile($doc->file_path);
                if (! $resolved) {
                    return null;
                }

                return [
                    'document_id' => 'ev-'.$doc->id,
                    'document_label' => $doc->name,
                    'document_type' => 'Dokument imprezy',
                    'relative_path' => $doc->file_path,
                    'absolute_path' => $resolved['absolute_path'],
                    'base_name' => $resolved['base_name'],
                    'zip_name' => $doc->name.'/'.$resolved['base_name'],
                ];
            })
            ->filter()
            ->values();

        $attachedFiles = $attachedFiles->merge($eventDocumentsAttached)->values();

        $insuranceAttached = collect();
        if (in_array($audience, ['pilot', 'folder'], true)) {
            $insuranceAttached = collect($event->insuranceFilesForPilot())
                ->map(function (array $file) {
                    $resolved = $this->resolveStoredFile((string) $file['path']);
                    if (! $resolved) {
                        return null;
                    }

                    $label = (string) $file['label'];

                    return [
                        'document_id' => 'insurance-'.$file['key'],
                        'document_label' => $label,
                        'document_type' => 'Ubezpieczenie',
                        'relative_path' => $file['path'],
                        'absolute_path' => $resolved['absolute_path'],
                        'base_name' => $resolved['base_name'],
                        'zip_name' => 'Ubezpieczenie/'.$label.'/'.$resolved['base_name'],
                    ];
                })
                ->filter()
                ->values();

            $attachedFiles = $attachedFiles->merge($insuranceAttached)->values();
        }

        $selectedDocumentsForView = $selectedDocuments
            ->map(function ($document) use ($attachedFiles) {
                $docLabel = $document->document_number ?: ('Dokument #'.$document->id);
                $files = $attachedFiles->where('document_id', $document->id)->values();

                return [
                    'id' => $document->id,
                    'label' => $docLabel,
                    'type' => EventSettlementDocument::$documentTypes[$document->document_type] ?? $document->document_type,
                    'vendor_name' => $document->vendor_name,
                    'files' => $files,
                ];
            })
            ->filter(fn ($document) => collect($document['files'])->isNotEmpty())
            ->values();

        foreach ($eventDocumentsAttached as $evDoc) {
            $selectedDocumentsForView->push([
                'id' => $evDoc['document_id'],
                'label' => $evDoc['document_label'],
                'type' => $evDoc['document_type'],
                'vendor_name' => null,
                'files' => collect([$evDoc]),
            ]);
        }

        foreach ($insuranceAttached as $insDoc) {
            $selectedDocumentsForView->push([
                'id' => $insDoc['document_id'],
                'label' => $insDoc['document_label'],
                'type' => $insDoc['document_type'],
                'vendor_name' => null,
                'files' => collect([$insDoc]),
            ]);
        }

        $documentFocus = [
            'pilot' => [
                'Harmonogram dzienny i godziny punktów programu',
                'Notatki pilota i notatki operacyjne biura',
                'Liczba uczestników i kontakt do biura/klienta',
                'Plan transportu i status płatności grupy',
                'Polisa i oryginalna lista ubezpieczonych (jeśli wgrane)',
            ],
            'hotel' => [
                'Daty przyjazdu/wyjazdu oraz liczebność grupy',
                'Rozpiska pokoi: uczestnicy, '.EventParticipantGroupLabels::GRATIS_GENITIVE.', obsługa, kierowca',
                'Uwagi do noclegu z podziałem na dzień',
                'Dane kontaktowe pilota i biura operacyjnego',
            ],
            'driver' => [
                'Trasa: miejsce startu, długość programu i transferów',
                'Harmonogram dnia z godzinami podstawienia',
                'Liczebność pasażerów i kontakt do pilota/biura',
                'Uwagi logistyczne i kolejność punktów programu',
            ],
            'folder' => [
                'Komplet danych imprezy: klient, terminy, status, koszty',
                'Program i notatki operacyjne (biuro + pilot)',
                'Pakiet noclegów i alokacja pokoi',
                'Umowy uczestników oraz podsumowanie płatności',
            ],
        ];

        return [
            'audience' => $audience,
            'audienceLabel' => self::AUDIENCE_LABELS[$audience] ?? $audience,
            'event' => $event,
            'company' => $company,
            'logoDataUri' => $logoDataUri,
            'generatedAt' => now(),
            'participantCount' => $participantCount,
            'staffCount' => $staffCount,
            'driverCount' => $driverCount,
            'gratisCount' => $gratisCount,
            'hotelNotes' => trim(strip_tags((string) ($event->hotel_notes ?? ''))),
            'hotelProgramPoints' => $event->hotelProgramPoints,
            'programByDay' => $programByDay,
            'pilotSetFinanceCards' => $pilotSetFinanceCards,
            'hotelPlan' => $hotelPlan,
            'usesEventHotelPlan' => $usesEventHotelPlan,
            'agreements' => $individualAgreementReport['agreements'],
            'individualAgreementRows' => $individualAgreementReport['rows'],
            'agreementsSummary' => $individualAgreementReport['summary'],
            'documentFocus' => $documentFocus[$audience] ?? [],
            'selectedSettlementDocuments' => $selectedDocumentsForView,
            'attachedFiles' => $attachedFiles,
            'travelLegends' => app(EventFolderPdfService::class)->buildTravelLegends($event),
            'programDayRoutes' => $event->programDayRoutes(),
            'participantSummaryLine' => sprintf(
                '%d uczestników + %d '.EventParticipantGroupLabels::GRATIS_GENITIVE.'; obsługa: %d; kierowca(y): %d',
                max(0, $participantCount),
                $gratisCount,
                $staffCount,
                $driverCount
            ),
        ];
    }

    public function buildProgramByDay(Event $event): Collection
    {
        return $event->programPoints
            ->where('include_in_program', true)
            ->values()
            ->groupBy(fn ($point) => (int) ($point->day ?? 1))
            ->sortKeys();
    }

    public function resolveLogoDataUri(?string $relativePath = null): ?string
    {
        $relativePath = $relativePath ?? (string) (config('company.logo_path') ?? 'uploads/logo.png');
        $path = public_path(ltrim($relativePath, '/'));

        if (! File::exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) File::get($path));
    }

    public function attachmentFlagForAudience(string $audience): string
    {
        return match ($audience) {
            'pilot' => 'attach_to_pilot_pdf',
            'hotel', 'hotel_agenda', 'hotel_agendas' => 'attach_to_hotel_pdf',
            'driver' => 'attach_to_driver_pdf',
            default => 'attach_to_folder_pdf',
        };
    }

    /**
     * @return array{disk: string, absolute_path: string, base_name: string}|null
     */
    public function resolveStoredFile(string $relativePath): ?array
    {
        $normalizedPath = StoragePath::normalize($relativePath);

        if (! $normalizedPath) {
            return null;
        }

        foreach (['public', config('filesystems.default')] as $diskName) {
            if (! $diskName) {
                continue;
            }

            $disk = Storage::disk((string) $diskName);

            if ($disk->exists($normalizedPath)) {
                return [
                    'disk' => (string) $diskName,
                    'absolute_path' => $disk->path($normalizedPath),
                    'base_name' => basename($normalizedPath),
                ];
            }
        }

        return null;
    }
}
