<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\EventSettlementDocument;
use App\Models\HotelRoom;
use App\Services\Documents\EventPackageFileSignedUrlService;
use App\Services\Documents\PilotPackageOperationalDataBuilder;
use App\Support\EventParticipantGroupLabels;
use App\Support\MoneyFormatter;
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
        'hotel' => 'Informacje dla Hotelu',
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
        $pilotExpenseRows = [];
        $pilotContactPlaces = [];
        $pilotDueByPointId = [];
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

        $signedUrls = app(EventPackageFileSignedUrlService::class);
        $linkExpiresAt = in_array($audience, EventPackageFileSignedUrlService::AUDIENCES, true)
            ? $signedUrls->expiresAt($event)
            : null;

        $attachedFiles = $selectedDocuments
            ->flatMap(function ($document) use ($event, $audience, $signedUrls, $linkExpiresAt) {
                $docLabel = $document->document_number ?: ('Dokument #'.$document->id);
                $files = array_values(array_filter(
                    $document->files ?? [],
                    static fn ($path): bool => is_string($path) && $path !== '',
                ));

                return collect($files)->map(function ($relativePath, $fileIndex) use ($docLabel, $document, $event, $audience, $signedUrls, $linkExpiresAt) {
                    $resolved = $this->resolveStoredFile((string) $relativePath);

                    if (! $resolved) {
                        return null;
                    }

                    $downloadUrl = null;
                    if ($linkExpiresAt && in_array($audience, EventPackageFileSignedUrlService::AUDIENCES, true)) {
                        $downloadUrl = $signedUrls->make($event, $audience, [
                            'kind' => EventPackageFileSignedUrlService::KIND_SETTLEMENT_DOCUMENT,
                            'ref' => $document->id,
                            'file_index' => (int) $fileIndex,
                        ]);
                    }

                    return [
                        'document_id' => $document->id,
                        'document_label' => $docLabel,
                        'document_type' => EventSettlementDocument::$documentTypes[$document->document_type] ?? $document->document_type,
                        'description' => trim((string) ($document->notes ?? '')),
                        'relative_path' => $relativePath,
                        'absolute_path' => $resolved['absolute_path'],
                        'base_name' => $resolved['base_name'],
                        'zip_name' => $docLabel.'/'.$resolved['base_name'],
                        'download_url' => $downloadUrl,
                        'link_expires_at' => $linkExpiresAt,
                    ];
                });
            })
            ->filter()
            ->values();

        $eventDocumentsAttached = $event->documents
            ->filter(fn ($doc) => (bool) ($doc->{$attachmentFlag} ?? false) && $doc->file_path)
            ->filter(fn ($doc) => ($doc->approval_status ?? 'pending') !== 'rejected')
            ->map(function ($doc) use ($event, $audience, $signedUrls, $linkExpiresAt) {
                $resolved = $this->resolveStoredFile($doc->file_path);
                if (! $resolved) {
                    return null;
                }

                $downloadUrl = null;
                if ($linkExpiresAt && in_array($audience, EventPackageFileSignedUrlService::AUDIENCES, true)) {
                    $downloadUrl = $signedUrls->make($event, $audience, [
                        'kind' => EventPackageFileSignedUrlService::KIND_EVENT_DOCUMENT,
                        'ref' => $doc->id,
                        'file_index' => 0,
                    ]);
                }

                return [
                    'document_id' => 'ev-'.$doc->id,
                    'document_label' => $doc->name,
                    'document_type' => 'Dokument imprezy',
                    'description' => trim((string) ($doc->notes ?? '')),
                    'relative_path' => $doc->file_path,
                    'absolute_path' => $resolved['absolute_path'],
                    'base_name' => $resolved['base_name'],
                    'zip_name' => $doc->name.'/'.$resolved['base_name'],
                    'download_url' => $downloadUrl,
                    'link_expires_at' => $linkExpiresAt,
                ];
            })
            ->filter()
            ->values();

        $attachedFiles = $attachedFiles->merge($eventDocumentsAttached)->values();

        $insuranceAttached = collect();
        if (in_array($audience, ['pilot', 'folder'], true)) {
            $insuranceAttached = collect($event->insuranceFilesForPilot())
                ->map(function (array $file) use ($event, $audience, $signedUrls, $linkExpiresAt) {
                    $resolved = $this->resolveStoredFile((string) $file['path']);
                    if (! $resolved) {
                        return null;
                    }

                    $label = (string) $file['label'];
                    $downloadUrl = null;
                    if ($linkExpiresAt) {
                        $downloadUrl = $signedUrls->make($event, $audience, [
                            'kind' => EventPackageFileSignedUrlService::KIND_INSURANCE,
                            'ref' => (string) $file['key'],
                            'file_index' => 0,
                        ]);
                    }

                    return [
                        'document_id' => 'insurance-'.$file['key'],
                        'document_label' => $label,
                        'document_type' => 'Ubezpieczenie',
                        'description' => '',
                        'relative_path' => $file['path'],
                        'absolute_path' => $resolved['absolute_path'],
                        'base_name' => $resolved['base_name'],
                        'zip_name' => 'Ubezpieczenie/'.$label.'/'.$resolved['base_name'],
                        'download_url' => $downloadUrl,
                        'link_expires_at' => $linkExpiresAt,
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

        $travelLegends = app(EventFolderPdfService::class)->buildTravelLegends($event);
        $programDayRoutes = $event->programDayRoutes();
        $driverDayRoutes = $audience === 'driver'
            ? $this->buildDriverDayRoutes($event, $programByDay, $travelLegends)
            : [];

        $settlementLedger = [];
        $settlementTotalsByCurrency = [];
        if ($audience === 'folder') {
            [$settlementLedger, $settlementTotalsByCurrency] = $this->buildSettlementLedger($event);
        }

        $pilotDutyText = '';
        if (in_array($audience, ['pilot', 'folder'], true)) {
            $pilotDutyText = $this->resolvePilotDutyText();
        }

        if (in_array($audience, ['pilot', 'folder'], true)) {
            $operational = app(PilotPackageOperationalDataBuilder::class);
            $pilotExpenseRows = $operational->expenseRows($event);
            $pilotDueByPointId = $operational->pilotDueByPointId($event);
            $pilotContactPlaces = $operational->contactPlaces(
                $event,
                $hotelPlan instanceof Collection ? $hotelPlan : collect($hotelPlan),
                $programByDay,
                $travelLegends,
                $programDayRoutes,
            );
        }

        $documentFocus = [
            'pilot' => [
                'Harmonogram dzienny i godziny punktów programu',
                'Adresy i kontakty (trasa, hotele, kontrahenci)',
                'Wydatki do zapłaty przez pilota',
                'Notatki pilota i notatki operacyjne biura',
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
            'dietInfoLines' => array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                preg_split("/\r\n|\n|\r/", (string) ($event->diet_info ?? '')) ?: [],
            ))),
            'hotelProgramPoints' => $event->hotelProgramPoints,
            'programByDay' => $programByDay,
            'pilotSetFinanceCards' => $pilotSetFinanceCards,
            'pilotExpenseRows' => $pilotExpenseRows,
            'pilotDueByPointId' => $pilotDueByPointId,
            'pilotContactPlaces' => $pilotContactPlaces,
            'hotelPlan' => $hotelPlan,
            'usesEventHotelPlan' => $usesEventHotelPlan,
            'agreements' => $individualAgreementReport['agreements'],
            'individualAgreementRows' => $individualAgreementReport['rows'],
            'agreementsSummary' => $individualAgreementReport['summary'],
            'documentFocus' => $documentFocus[$audience] ?? [],
            'selectedSettlementDocuments' => $selectedDocumentsForView,
            'attachedFiles' => $attachedFiles,
            'travelLegends' => $travelLegends,
            'programDayRoutes' => $programDayRoutes,
            'driverDayRoutes' => $driverDayRoutes,
            'settlementLedger' => $settlementLedger,
            'settlementTotalsByCurrency' => $settlementTotalsByCurrency,
            'pilotDutyText' => $pilotDutyText,
            'packageFileLinksExpireAt' => $linkExpiresAt,
            'participantCompactLine' => sprintf(
                '%d+%d',
                max(0, $participantCount),
                max(0, $gratisCount),
            ),
            // Kierowca: krótki format operacyjny „16+1”. Reszta: pełny opis jak na branchu.
            'participantSummaryLine' => $audience === 'driver'
                ? sprintf('%d+%d', max(0, $participantCount), max(0, $gratisCount))
                : sprintf(
                    '%d uczestników + %d %s; obsługa: %d; kierowca(y): %d',
                    max(0, $participantCount),
                    max(0, $gratisCount),
                    EventParticipantGroupLabels::GRATIS_GENITIVE,
                    $staffCount,
                    $driverCount
                ),
        ];
    }

    /**
     * Trasa dzień po dniu dla kierowcy — bez opisów atrakcji.
     *
     * @param  array<string, mixed>  $travelLegends
     * @return list<array{day: int, date: string, time: string, from: string, to: string, route: string}>
     */
    public function buildDriverDayRoutes(Event $event, Collection $programByDay, array $travelLegends = []): array
    {
        $rows = [];
        $dayKeys = $programByDay->keys()->map(fn ($d) => (int) $d)->sort()->values();

        if ($dayKeys->isEmpty()) {
            $core = max(1, (int) $event->resolveCoreProgramDaysCount());
            $dayKeys = collect(range(1, $core));
        }

        foreach ($dayKeys as $day) {
            $date = $event->dateForProgramDay((int) $day);
            $route = (string) ($event->programDayRoute((int) $day) ?? '');
            $time = $event->programDayStartTimeLabel((int) $day);

            $from = '—';
            $to = '—';

            if ($route !== '' && str_contains($route, '→')) {
                [$fromRaw, $toRaw] = array_map('trim', explode('→', $route, 2));
                $from = $fromRaw !== '' ? $fromRaw : '—';
                $to = $toRaw !== '' ? $toRaw : '—';
            } elseif ($route !== '' && str_contains($route, '->')) {
                [$fromRaw, $toRaw] = array_map('trim', explode('->', $route, 2));
                $from = $fromRaw !== '' ? $fromRaw : '—';
                $to = $toRaw !== '' ? $toRaw : '—';
            } elseif ($route !== '') {
                $from = $route;
                $to = $route;
            } else {
                $points = collect($programByDay->get($day) ?? $programByDay->get((string) $day) ?? []);
                $names = $points
                    ->map(fn ($point) => trim((string) ($point->name ?: (optional($point->templatePoint)->name ?? ''))))
                    ->filter()
                    ->values();
                if ($names->isNotEmpty()) {
                    $from = (string) $names->first();
                    $to = (string) $names->last();
                } elseif ((int) $day === 1) {
                    $from = trim(strip_tags((string) ($event->adress_transport_start ?: $event->pickup_place_details ?: $event->startPlace?->name ?: ''))) ?: '—';
                    $to = trim((string) ($travelLegends['destination'] ?? '')) ?: '—';
                    if (str_contains($to, "\n")) {
                        $to = trim(explode("\n", $to)[0]);
                    }
                }
            }

            if ($time === '' && (int) $day === 1 && filled($event->departure_time)) {
                $time = substr((string) $event->departure_time, 0, 5);
            }

            $rows[] = [
                'day' => (int) $day,
                'date' => $date?->format('d.m.Y') ?? '—',
                'time' => $time !== '' ? $time : '—',
                'from' => $from,
                'to' => $to,
                'route' => $route !== '' ? $route : trim($from.' → '.$to, ' →'),
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array{currency: string, total_label: string}>}
     */
    public function buildSettlementLedger(Event $event): array
    {
        $documents = collect($event->activeSettlement?->documents ?? [])
            ->filter(fn ($document) => ($document->approval_status ?? 'pending') !== 'rejected')
            ->sortBy(fn ($document) => [(string) ($document->issue_date?->format('Y-m-d') ?? '9999'), (int) $document->id])
            ->values();

        $ledger = [];
        $totals = [];

        foreach ($documents as $document) {
            $currency = MoneyFormatter::currencyCode($document->currency);
            $amount = (float) ($document->total_amount ?? 0);
            $totals[$currency] = ($totals[$currency] ?? 0) + $amount;

            $ledger[] = [
                'number' => $document->document_number ?: ('#'.$document->id),
                'type' => EventSettlementDocument::$documentTypes[$document->document_type] ?? $document->document_type,
                'vendor' => $document->vendor_name ?: '—',
                'issue_date' => $document->issue_date?->format('d.m.Y') ?? '—',
                'amount_label' => MoneyFormatter::format($amount, $currency),
                'currency' => $currency,
            ];
        }

        $totalsByCurrency = [];
        foreach ($totals as $currency => $sum) {
            $totalsByCurrency[] = [
                'currency' => $currency,
                'total_label' => MoneyFormatter::format($sum, $currency),
            ];
        }

        return [$ledger, $totalsByCurrency];
    }

    public function resolvePilotDutyText(): string
    {
        $value = AppSetting::getValue('pilot_duty_text', '');

        if (is_array($value)) {
            $value = (string) ($value['content'] ?? $value['text'] ?? '');
        }

        return trim(strip_tags((string) $value));
    }

    public function buildProgramByDay(Event $event): Collection
    {
        $coreDays = $event->resolveCoreProgramDaysCount();

        // Bez slotu fakultatywnego — to opcje pod stronę/szablon, nie dzień wycieczki w PDF.
        return $event->programPoints
            ->where('include_in_program', true)
            ->filter(fn ($point) => (int) ($point->day ?? 1) <= $coreDays)
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
