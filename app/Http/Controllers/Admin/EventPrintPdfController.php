<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPackageDocument;
use App\Services\Documents\HotelAgendaDataBuilder;
use App\Services\EventDocumentGeneratorService;
use App\Services\EventPackageDocumentService;
use App\Services\EventPrintPdfDataFactory;
use App\Support\DomPdfFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * PDF-y pakietów imprezy — szablony w resources/views/pdf/packages oraz documents/*.
 */
class EventPrintPdfController extends Controller
{
    public function __construct(
        private readonly EventPrintPdfDataFactory $dataFactory,
        private readonly EventDocumentGeneratorService $documentGenerator,
        private readonly EventPackageDocumentService $packageDocuments,
        private readonly HotelAgendaDataBuilder $hotelAgendaBuilder,
    ) {}

    public function download(Request $request, Event $event, string $audience)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $event);

        abort_unless(array_key_exists($audience, EventPrintPdfDataFactory::AUDIENCE_LABELS), 404);

        if (in_array($audience, ['program_with_times', 'program_without_times'], true)) {
            $event->load([
                'programPoints' => fn ($query) => $query->with('templatePoint')->orderBy('day')->orderBy('order'),
            ]);

            $showTimes = $audience === 'program_with_times';
            $company = config('company', []);
            $data = [
                'audienceLabel' => EventPrintPdfDataFactory::AUDIENCE_LABELS[$audience],
                'event' => $event,
                'company' => $company,
                'logoDataUri' => $this->dataFactory->resolveLogoDataUri(),
                'generatedAt' => now(),
                'programByDay' => $this->dataFactory->buildProgramByDay($event),
                'showTimes' => $showTimes,
            ];

            return DomPdfFactory::loadView('pdf.packages.program', $data)
                ->download($this->filename($event, $audience));
        }

        $this->loadEventForPackages($event);

        if ($audience === 'all') {
            $zipRelative = $this->documentGenerator->downloadFullPackageZip($event);

            return response()->download(
                Storage::disk('local')->path($zipRelative),
                $this->safeZipName($event, 'komplet-dokumentow'),
            )->deleteFileAfterSend(false);
        }

        if ($audience === 'hotel_agendas') {
            return $this->downloadHotelAgendasZip($event);
        }

        if ($audience === 'hotel_agenda') {
            return $this->downloadSingleHotelAgenda($request, $event);
        }

        if (in_array($audience, EventPackageDocument::AUDIENCES, true)) {
            return $this->downloadEditablePackage($request, $event, $audience);
        }

        abort(404);
    }

    private function downloadEditablePackage(Request $request, Event $event, string $audience)
    {
        $resolved = $this->documentGenerator->downloadPackage($event, $audience);
        $inline = $request->boolean('preview');

        $attachmentFiles = collect($resolved['attachedFiles'] ?? []);

        if ($attachmentFiles->isEmpty() || $inline) {
            $disposition = $inline ? 'inline' : 'attachment';

            return response($resolved['binary'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.$resolved['filename'].'"',
            ]);
        }

        return $this->downloadZipBundle($event, $audience, $resolved['binary'], $attachmentFiles);
    }

    private function downloadSingleHotelAgenda(Request $request, Event $event): BinaryFileResponse|RedirectResponse
    {
        $contractorId = (int) $request->query('contractor_id', 0);
        $locationRaw = $request->query('location_id');
        $locationId = $locationRaw === null || $locationRaw === '' ? null : (int) $locationRaw;

        if ($contractorId <= 0) {
            $first = $this->hotelAgendaBuilder->hotelsForEvent($event)->first();
            if (! $first) {
                return $this->softFail(
                    $event,
                    'Brak hoteli z kontrahentem w planie imprezy — uzupełnij noclegi, potem wygeneruj agendy.'
                );
            }
            $contractorId = (int) $first['contractor_id'];
            $locationId = $first['contractor_location_id'];
        }

        $generated = $this->documentGenerator->generateSingleHotelAgenda($event, $contractorId, $locationId);
        if (! $generated) {
            return $this->softFail($event, 'Nie znaleziono agendy dla wskazanego hotelu.');
        }

        return response()->download(
            $generated['absolute_path'],
            $generated['download_name'],
        );
    }

    private function downloadHotelAgendasZip(Event $event): BinaryFileResponse|RedirectResponse
    {
        $agendas = $this->documentGenerator->generateHotelAgendas($event);
        if ($agendas === []) {
            return $this->softFail(
                $event,
                'Brak hoteli z kontrahentem w planie imprezy — uzupełnij noclegi, potem wygeneruj agendy.'
            );
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'hotel_agendas_');
        abort_unless($zipPath !== false, 500);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Nie udało się utworzyć ZIP agend hotelowych.');
        }

        foreach ($agendas as $agenda) {
            $zip->addFile(
                $agenda['absolute_path'],
                $agenda['download_name'],
            );
        }
        $zip->close();

        return response()->download(
            $zipPath,
            $this->safeZipName($event, 'agendy-hotelowe'),
        )->deleteFileAfterSend(true);
    }

    private function softFail(Event $event, string $message): RedirectResponse
    {
        return redirect()
            ->to(\App\Filament\Resources\EventResource::getUrl('documents', ['record' => $event]))
            ->with('filament.notifications', [[
                'title' => 'Nie można wygenerować dokumentu',
                'body' => $message,
                'status' => 'warning',
            ]])
            ->with('error', $message);
    }

    private function loadEventForPackages(Event $event): void
    {
        $event->load([
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
            'programPoints' => fn ($query) => $query->with(['templatePoint', 'contractor', 'contractorLocation'])->orderBy('day')->orderBy('order'),
            'agreements',
            'qtyVariants',
        ]);
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

    private function filename(Event $event, string $audience): string
    {
        if (in_array($audience, EventPackageDocument::AUDIENCES, true)) {
            return $this->packageDocuments->filename($event, $audience);
        }

        $safeName = str($event->name ?: 'impreza')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->value();

        $label = match ($audience) {
            'hotel_agenda' => 'agenda-hotelu',
            default => $audience,
        };

        return sprintf('%s-%s-%d.pdf', $safeName ?: 'impreza', $label, $event->id);
    }

    private function safeZipName(Event $event, string $suffix): string
    {
        $safeName = str($event->name ?: 'impreza')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->value();

        return sprintf('%s-%s-%d.zip', $safeName ?: 'impreza', $suffix, $event->id);
    }
}
