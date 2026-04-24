<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSettlementDocument;
use App\Models\HotelRoom;
use App\Support\StoragePath;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EventPrintPdfController extends Controller
{
    private const AUDIENCES = [
        'pilot' => 'Pakiet dla pilota',
        'hotel' => 'Pakiet dla hotelu',
        'driver' => 'Pakiet dla kierowcy',
        'folder' => 'Teczka imprezy',
        'all' => 'Komplet pakietów',
    ];

    public function download(Event $event, string $audience)
    {
        abort_unless(array_key_exists($audience, self::AUDIENCES), 404);

        $event->load([
            'eventTemplate.hotelDays',
            'startPlace',
            'bus',
            'assignedUser',
            'creator',
            'contractor',
            'activeSettlement.documents',
            'documents',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'agreements' => fn ($query) => $query->orderByDesc('agreement_date'),
        ]);

        if ($audience === 'all') {
            return $this->downloadAllPackagesBundle($event);
        }

        $data = $this->buildDocumentData($event, $audience);

        $pdf = Pdf::loadView('pdf.event-document', $data)
            ->setPaper('a4');

        $attachmentFiles = collect($data['attachedFiles'] ?? []);

        if ($attachmentFiles->isEmpty()) {
            return $pdf->download($this->filename($event, $audience));
        }

        return $this->downloadZipBundle($event, $audience, $pdf->output(), $attachmentFiles);
    }

    private function buildDocumentData(Event $event, string $audience): array
    {
        $company = config('company', []);
        $logoDataUri = $this->resolveLogoDataUri((string) ($company['logo_path'] ?? 'uploads/logo.png'));

        $participantCount = (int) ($event->participant_count ?? 0);
        $qtyVariant = $event->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [max(1, $participantCount)])
            ->first();

        $staffCount = (int) ($qtyVariant->staff ?? 0);
        $driverCount = max(1, (int) ($qtyVariant->driver ?? 1));
        $gratisCount = (int) ($qtyVariant->gratis ?? 0);

        $programPoints = $event->programPoints
            ->where('include_in_program', true)
            ->values();

        $programByDay = $programPoints
            ->groupBy(fn ($point) => (int) ($point->day ?? 1))
            ->sortKeys();

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
            ];
        });

        $individualAgreementReport = $event->buildIndividualAgreementReport($event->agreements);
        $agreements = $individualAgreementReport['agreements'];
        $agreementsSummary = $individualAgreementReport['summary'];
        $individualAgreementRows = $individualAgreementReport['rows'];

        $documentFocus = [
            'pilot' => [
                'Harmonogram dzienny i godziny punktów programu',
                'Notatki pilota i notatki operacyjne biura',
                'Liczba uczestników i kontakt do biura/klienta',
                'Plan transportu i status płatności grupy',
            ],
            'hotel' => [
                'Daty przyjazdu/wyjazdu oraz liczebność grupy',
                'Rozpiska pokoi: uczestnicy, gratisy, obsługa, kierowca',
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

        $attachmentFlag = $this->attachmentFlagForAudience($audience);
        $selectedDocuments = collect($event->activeSettlement?->documents ?? [])
            ->filter(fn ($document) => (bool) ($document->{$attachmentFlag} ?? false))
            ->filter(fn ($document) => ($document->approval_status ?? 'pending') === 'approved')
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

        // Dołącz dokumenty bezpośrednio przypisane do imprezy
        $eventDocumentsAttached = $event->documents
            ->filter(fn ($doc) => (bool) ($doc->{$attachmentFlag} ?? false) && $doc->file_path)
            ->filter(fn ($doc) => ($doc->approval_status ?? 'pending') === 'approved')
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

        // Dodaj dokumenty imprezy do widoku
        foreach ($eventDocumentsAttached as $evDoc) {
            $selectedDocumentsForView->push([
                'id' => $evDoc['document_id'],
                'label' => $evDoc['document_label'],
                'type' => $evDoc['document_type'],
                'vendor_name' => null,
                'files' => collect([$evDoc]),
            ]);
        }

        return [
            'audience' => $audience,
            'audienceLabel' => self::AUDIENCES[$audience],
            'event' => $event,
            'company' => $company,
            'logoDataUri' => $logoDataUri,
            'generatedAt' => now(),
            'participantCount' => $participantCount,
            'staffCount' => $staffCount,
            'driverCount' => $driverCount,
            'gratisCount' => $gratisCount,
            'hotelNotes' => trim(strip_tags((string) ($event->hotel_notes ?? ''))),
            'programByDay' => $programByDay,
            'hotelPlan' => $hotelPlan,
            'agreements' => $agreements,
            'individualAgreementRows' => $individualAgreementRows,
            'agreementsSummary' => $agreementsSummary,
            'documentFocus' => $documentFocus[$audience] ?? [],
            'selectedSettlementDocuments' => $selectedDocumentsForView,
            'attachedFiles' => $attachedFiles,
        ];
    }

    private function attachmentFlagForAudience(string $audience): string
    {
        return match ($audience) {
            'pilot' => 'attach_to_pilot_pdf',
            'hotel' => 'attach_to_hotel_pdf',
            'driver' => 'attach_to_driver_pdf',
            default => 'attach_to_folder_pdf',
        };
    }

    private function resolveStoredFile(string $relativePath): ?array
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

    private function downloadZipBundle(Event $event, string $audience, string $pdfBinary, $attachmentFiles)
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'event_pdf_bundle_');

        if ($zipPath === false) {
            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->filename($event, $audience).'"',
            ]);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->filename($event, $audience).'"',
            ]);
        }

        $zip->addFromString($this->filename($event, $audience), $pdfBinary);

        foreach ($attachmentFiles as $attachment) {
            $absolutePath = $attachment['absolute_path'] ?? null;
            $zipName = $attachment['zip_name'] ?? null;

            if (! $absolutePath || ! $zipName || ! is_file($absolutePath)) {
                continue;
            }

            $zip->addFile($absolutePath, 'zalaczniki/'.$zipName);
        }

        $zip->close();

        $zipFilename = str_replace('.pdf', '-pakiet.zip', $this->filename($event, $audience));

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
    }

    private function downloadAllPackagesBundle(Event $event)
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'event_all_pdf_bundle_');

        if ($zipPath === false) {
            abort(500, 'Nie udało się utworzyć paczki ZIP.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Nie udało się otworzyć paczki ZIP.');
        }

        foreach (['pilot', 'hotel', 'driver', 'folder'] as $singleAudience) {
            $data = $this->buildDocumentData($event, $singleAudience);
            $pdf = Pdf::loadView('pdf.event-document', $data)
                ->setPaper('a4')
                ->output();

            $baseDir = 'pakiet-'.$singleAudience;
            $zip->addFromString($baseDir.'/'.$this->filename($event, $singleAudience), $pdf);

            foreach (collect($data['attachedFiles'] ?? []) as $attachment) {
                $absolutePath = $attachment['absolute_path'] ?? null;
                $zipName = $attachment['zip_name'] ?? null;

                if (! $absolutePath || ! $zipName || ! is_file($absolutePath)) {
                    continue;
                }

                $zip->addFile($absolutePath, $baseDir.'/zalaczniki/'.$zipName);
            }
        }

        $zip->close();

        $safeName = str($event->name ?: 'impreza')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->value();

        $zipFilename = sprintf('%s-komplet-pakietow-%d.zip', $safeName ?: 'impreza', $event->id);

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
    }

    private function resolveLogoDataUri(string $relativePath): ?string
    {
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

    private function filename(Event $event, string $audience): string
    {
        $safeName = str($event->name ?: 'impreza')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->value();

        return sprintf('%s-%s-%d.pdf', $safeName ?: 'impreza', $audience, $event->id);
    }
}
