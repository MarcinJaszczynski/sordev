<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventPackageDocument;
use App\Services\Documents\HotelAgendaDataBuilder;
use App\Support\DomPdfFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Generowanie dokumentów PDF imprezy: agendy hotelowe, teczka kierowcy, komplet ZIP.
 *
 * Fasada dokumentów pakietowych — kontrolery HTTP i UI powinny wołać tę klasę
 * zamiast sklejać EventPackageDocumentService + ZIP samodzielnie.
 */
final class EventDocumentGeneratorService
{
    public function __construct(
        private readonly HotelAgendaDataBuilder $hotelAgendaBuilder,
        private readonly EventPackageDocumentService $packageDocuments,
    ) {}

    /**
     * PDF pakietu (pilot/hotel/driver/folder) z trybem live/overrides/frozen/upload.
     *
     * @return array{binary: string, filename: string, attachedFiles?: list<array{path: string, name: string}>}
     */
    public function downloadPackage(Event $event, string $audience): array
    {
        return $this->packageDocuments->resolveDownload($event, $audience);
    }

    /**
     * Komplet pakietów → ścieżka względna ZIP na dysku local.
     */
    public function downloadFullPackageZip(Event $event): string
    {
        return $this->generateFullTripPackage($event);
    }

    /**
     * @return list<array{path: string, absolute_path: string, hotel_name: string, contractor_id: int, contractor_location_id: int|null}>
     */
    public function generateHotelAgendas(Event $event): array
    {
        $this->ensureEventLoaded($event);
        $dir = $this->eventDirectory($event);
        $results = [];

        foreach ($this->hotelAgendaBuilder->hotelsForEvent($event) as $hotel) {
            $data = $this->hotelAgendaBuilder->build(
                $event,
                (int) $hotel['contractor_id'],
                $hotel['contractor_location_id'] !== null ? (int) $hotel['contractor_location_id'] : null,
            );
            $data['logoDataUri'] = $this->resolveLogoDataUri();

            $relative = $dir.'/hotel_agenda_'.$hotel['key'].'.pdf';
            $binary = DomPdfFactory::loadView('documents.hotel-agenda', $data)->output();

            Storage::disk('local')->put($relative, $binary);

            $results[] = [
                'path' => $relative,
                'absolute_path' => Storage::disk('local')->path($relative),
                'hotel_name' => (string) $hotel['name'],
                'contractor_id' => (int) $hotel['contractor_id'],
                'contractor_location_id' => $hotel['contractor_location_id'] !== null
                    ? (int) $hotel['contractor_location_id']
                    : null,
                'download_name' => $this->safeFilename(
                    'agenda-hotel-'.($hotel['name'] ?: 'hotel').'-'.$event->id.'.pdf'
                ),
            ];
        }

        return $results;
    }

    public function generateDriverDocument(Event $event): string
    {
        $this->ensureEventLoaded($event);
        $resolved = $this->packageDocuments->resolveDownload($event, EventPackageDocument::AUDIENCE_DRIVER);

        $relative = $this->eventDirectory($event).'/driver_info.pdf';
        Storage::disk('local')->put($relative, $resolved['binary']);

        return $relative;
    }

    /**
     * Komplet: pilot + hotel + kierowca + teczka → ZIP (z trybami edycji pakietów).
     */
    public function generateFullTripPackage(Event $event): string
    {
        $this->ensureEventLoaded($event);
        $dir = $this->eventDirectory($event);
        $zipRelative = $dir.'/komplet-dokumentow-'.$event->id.'.zip';
        $zipAbsolute = Storage::disk('local')->path($zipRelative);

        File::ensureDirectoryExists(dirname($zipAbsolute));

        $zip = new ZipArchive;
        if ($zip->open($zipAbsolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nie udało się utworzyć archiwum ZIP dokumentów imprezy.');
        }

        foreach (EventPackageDocument::AUDIENCES as $audience) {
            $resolved = $this->packageDocuments->resolveDownload($event, $audience);
            $folder = match ($audience) {
                EventPackageDocument::AUDIENCE_DRIVER => 'kierowca',
                EventPackageDocument::AUDIENCE_FOLDER => 'teczka',
                default => $audience,
            };
            $zip->addFromString(
                $folder.'/'.$this->safeFilename($resolved['filename']),
                $resolved['binary']
            );
        }

        $zip->close();

        return $zipRelative;
    }

    /**
     * @return array{path: string, absolute_path: string, download_name: string}|null
     */
    public function generateSingleHotelAgenda(Event $event, int $contractorId, ?int $locationId = null): ?array
    {
        $this->ensureEventLoaded($event);

        try {
            $data = $this->hotelAgendaBuilder->build($event, $contractorId, $locationId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $data['logoDataUri'] = $this->resolveLogoDataUri();
        $key = $this->hotelAgendaBuilder->hotelKey($contractorId, $locationId);
        $relative = $this->eventDirectory($event).'/hotel_agenda_'.$key.'.pdf';
        $binary = DomPdfFactory::loadView('documents.hotel-agenda', $data)->output();

        Storage::disk('local')->put($relative, $binary);

        return [
            'path' => $relative,
            'absolute_path' => Storage::disk('local')->path($relative),
            'download_name' => $this->safeFilename(
                'agenda-hotel-'.($data['hotel']['name'] ?? 'hotel').'-'.$event->id.'.pdf'
            ),
        ];
    }

    public function driverDocumentAbsolutePath(Event $event): string
    {
        $relative = $this->generateDriverDocument($event);

        return Storage::disk('local')->path($relative);
    }

    private function eventDirectory(Event $event): string
    {
        $dir = 'documents/'.$event->id;
        Storage::disk('local')->makeDirectory($dir);

        return $dir;
    }

    private function ensureEventLoaded(Event $event): void
    {
        $event->loadMissing([
            'eventTemplate.hotelDays',
            'hotelStays.roomLines.occupants',
            'hotelStays.roomLines.hotelRoom',
            'hotelStays.roomLines.currency',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.programPoint',
            'startPlace',
            'bus',
            'assignedUser',
            'creator',
            'contractor',
            'transportContractor',
            'activeSettlement.documents',
            'documents',
            'packageDocuments',
            'hotelProgramPoints.contractor',
            'hotelProgramPoints.contractorLocation',
            'hotelProgramPoints.templatePoint',
            'programPoints' => fn ($query) => $query
                ->with(['templatePoint', 'contractor', 'contractorLocation'])
                ->orderBy('day')
                ->orderBy('order'),
            'agreements',
            'qtyVariants',
        ]);
    }

    private function resolveLogoDataUri(): ?string
    {
        $relativePath = (string) (config('company.logo_path') ?? 'uploads/logo.png');
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

    private function safeFilename(string $name): string
    {
        return (string) str($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/i', '-')
            ->trim('-');
    }
}
