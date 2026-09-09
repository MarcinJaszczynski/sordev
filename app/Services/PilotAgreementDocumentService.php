<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContractorSettlementForm;
use App\Mail\PilotCivilContractMail;
use App\Models\Contractor;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventPilotAgreement;
use App\Support\DomPdfFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generowanie / wysyłka / udostępnienie umowy cywilnoprawnej z pilotem.
 * Tożsamość: kontrahent. Portal i User są opcjonalne.
 */
class PilotAgreementDocumentService
{
    public function __construct(
        private readonly AgreementTemplateRenderer $renderer,
        private readonly PilotContractorAssignmentService $assignment,
        private readonly PilotFeeService $fees,
    ) {}

    public function tableReady(): bool
    {
        return Schema::hasTable('event_pilot_agreements')
            && Schema::hasTable('contract_templates');
    }

    public function currentForEvent(Event $event): ?EventPilotAgreement
    {
        if (! $this->tableReady()) {
            return null;
        }

        return EventPilotAgreement::query()
            ->where('event_id', $event->id)
            ->first();
    }

    /**
     * Generuje (lub regeneruje) PDF umowy dla imprezy.
     */
    public function generate(Event $event, ?int $templateId = null): EventPilotAgreement
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('Tabela umów pilota nie jest dostępna.');
        }

        $contractorId = $this->assignment->resolveContractorIdForEvent($event);
        if (! $contractorId) {
            throw new \InvalidArgumentException('Przypisz kontrahenta-pilota przed generowaniem umowy.');
        }

        $contractor = Contractor::query()->findOrFail($contractorId);
        $form = $event->resolvePilotSettlementForm() ?? ContractorSettlementForm::ContractOfWork;

        $template = $this->resolveTemplate($templateId);
        $agreement = $this->currentForEvent($event) ?? new EventPilotAgreement(['event_id' => $event->id]);

        $contractNumber = filled($agreement->contract_number)
            ? (string) $agreement->contract_number
            : $this->nextContractNumber($event);

        $payload = $this->buildPayload($event, $contractor, $form);
        $payload['agreement_number'] = $contractNumber;
        $bodyHtml = $this->renderer->render($template, $payload);

        $pdfBytes = $this->renderPdfBytes($contractNumber, $bodyHtml, $event, $contractor, $form);
        $path = $this->storePdf($event, $pdfBytes, $agreement->pdf_path);

        $agreement->fill([
            'contractor_id' => $contractor->id,
            'contract_template_id' => $template?->id,
            'settlement_form' => $form,
            'contract_number' => $contractNumber,
            'body_html' => $bodyHtml,
            'pdf_path' => $path,
            'generated_at' => now(),
            'generated_by' => Auth::id(),
        ]);
        $agreement->save();

        if ($agreement->shared_in_portal) {
            $this->syncPortalDocument($agreement->fresh() ?? $agreement);
        }

        return $agreement->fresh(['contractor', 'template', 'eventDocument']) ?? $agreement;
    }

    public function readPdfBytes(EventPilotAgreement $agreement): ?string
    {
        if (! $agreement->hasPdf()) {
            return null;
        }

        return Storage::disk('public')->get($agreement->pdf_path);
    }

    public function downloadFilename(EventPilotAgreement $agreement): string
    {
        $base = $agreement->contract_number
            ?: ('umowa-pilota-'.$agreement->event_id);

        return Str::slug($base, '-').'.pdf';
    }

    /**
     * Wysyłka PDF na e-mail kontrahenta (bez wymogu konta User).
     */
    public function sendEmail(Event $event): EventPilotAgreement
    {
        $agreement = $this->currentForEvent($event);
        if (! $agreement?->hasPdf()) {
            $agreement = $this->generate($event);
        }

        $agreement->loadMissing('contractor');
        $email = $agreement->contractor?->email;

        if (! filled($email)) {
            throw new \InvalidArgumentException('Kontrahent-pilot nie ma adresu e-mail.');
        }

        $bytes = $this->readPdfBytes($agreement);
        if ($bytes === null) {
            throw new \RuntimeException('Brak pliku PDF umowy.');
        }

        Mail::to($email)->send(new PilotCivilContractMail(
            event: $event->fresh() ?? $event,
            agreement: $agreement,
            pilotName: (string) ($agreement->contractor?->name ?? ''),
            settlementFormLabel: $agreement->settlementFormLabel(),
            pdfBytes: $bytes,
            pdfFilename: $this->downloadFilename($agreement),
        ));

        $agreement->update([
            'sent_at' => now(),
            'sent_by' => Auth::id(),
        ]);

        return $agreement->fresh() ?? $agreement;
    }

    /**
     * Udostępnia umowę w panelu pilota (EventDocument + attach_to_pilot_pdf).
     */
    public function shareInPortal(Event $event, bool $share = true): EventPilotAgreement
    {
        $agreement = $this->currentForEvent($event);
        if (! $agreement?->hasPdf()) {
            $agreement = $this->generate($event);
        }

        if (! $share) {
            $this->detachPortalDocument($agreement);
            $agreement->update([
                'shared_in_portal' => false,
                'event_document_id' => null,
            ]);

            return $agreement->fresh() ?? $agreement;
        }

        $agreement->update(['shared_in_portal' => true]);
        $this->syncPortalDocument($agreement->fresh() ?? $agreement);

        return $agreement->fresh(['eventDocument']) ?? $agreement;
    }

    public function canGenerate(Event $event): bool
    {
        return $this->tableReady()
            && $this->assignment->eventHasAssignedPilot($event)
            && filled($this->assignment->resolveContractorIdForEvent($event));
    }

    /**
     * @return array<string, string>
     */
    public function buildPayload(Event $event, Contractor $contractor, ContractorSettlementForm $form): array
    {
        $address = collect([
            trim(implode(' ', array_filter([(string) $contractor->street, (string) $contractor->house_number]))),
            trim(implode(' ', array_filter([(string) $contractor->postal_code, (string) $contractor->city]))),
        ])->filter()->implode(', ');

        $feeLabel = $this->fees->formatDueLabel($event);
        if ($feeLabel === '—') {
            $feeLabel = '';
        }

        return [
            'agreement_number' => '',
            'agreement_date' => now()->format('d.m.Y'),
            'agreement_type_label' => $form->label(),
            'event_name' => (string) ($event->name ?? '—'),
            'event_start_date' => $event->start_date?->format('d.m.Y') ?? '—',
            'event_end_date' => $event->end_date?->format('d.m.Y') ?? '—',
            'participant_count' => (string) max(1, (int) ($event->participant_count ?? 1)),
            'departure_place' => (string) ($event->startPlace?->name ?? '—'),
            'departure_date' => $event->start_date?->format('d.m.Y') ?? '—',
            'return_date' => $event->end_date?->format('d.m.Y') ?? '—',
            'pilot_name' => (string) ($contractor->name ?? '—'),
            'pilot_email' => (string) ($contractor->email ?? '—'),
            'pilot_phone' => (string) ($contractor->phone ?? '—'),
            'pilot_pesel' => (string) ($contractor->pesel ?? '—'),
            'pilot_birth_date' => $contractor->birth_date?->format('d.m.Y') ?? '—',
            'pilot_nip' => (string) ($contractor->nip ?? '—'),
            'pilot_bank_account' => (string) ($contractor->bank_account ?? '—'),
            'pilot_address' => $address !== '' ? $address : '—',
            'pilot_settlement_form' => $form->label(),
            'pilot_fee' => $feeLabel !== '' ? $feeLabel : '—',
            'customer_name' => (string) ($contractor->name ?? '—'),
            'customer_email' => (string) ($contractor->email ?? '—'),
            'customer_phone' => (string) ($contractor->phone ?? '—'),
            'organizer_name' => (string) config('company.name', config('app.name', 'Organizator')),
            'organizer_address_line_1' => (string) config('company.address_line_1', '—'),
            'organizer_address_line_2' => (string) config('company.address_line_2', '—'),
            'organizer_email' => (string) config('company.email', '—'),
            'organizer_phone' => (string) config('company.phone', '—'),
        ];
    }

    protected function resolveTemplate(?int $templateId): ?ContractTemplate
    {
        if ($templateId) {
            $template = ContractTemplate::query()->find($templateId);
            if ($template) {
                return $template;
            }
        }

        $explicit = ContractTemplate::query()
            ->active()
            ->orderBy('name')
            ->orderByDesc('version')
            ->get()
            ->first(fn (ContractTemplate $t): bool => is_array($t->applies_to)
                && in_array(ContractTemplate::APPLIES_PILOT, $t->applies_to, true));

        return $explicit ?? $this->ensureDefaultTemplate();
    }

    protected function ensureDefaultTemplate(): ContractTemplate
    {
        $existing = ContractTemplate::query()
            ->where('name', 'Umowa o dzieło — pilot (domyślna)')
            ->orderByDesc('version')
            ->first();

        if ($existing) {
            return $existing;
        }

        return ContractTemplate::query()->create([
            'name' => 'Umowa o dzieło — pilot (domyślna)',
            'applies_to' => [ContractTemplate::APPLIES_PILOT],
            'is_active' => true,
            'version' => 1,
            'content' => $this->defaultTemplateHtml(),
        ]);
    }

    protected function defaultTemplateHtml(): string
    {
        return <<<'HTML'
<p><strong>Umowa o dzieło</strong> nr [NUMER_UMOWY] zawarta w dniu [DATA_UMOWY]</p>
<p>pomiędzy:</p>
<p><strong>[ORGANIZATOR_NAZWA]</strong><br>[ORGANIZATOR_ADRES_1]<br>[ORGANIZATOR_ADRES_2]<br>e-mail: [ORGANIZATOR_EMAIL], tel. [ORGANIZATOR_TELEFON]<br>(„Zleceniodawca”)</p>
<p>a</p>
<p><strong>[PILOT_IMIE_NAZWISKO]</strong><br>PESEL: [PILOT_PESEL], data urodzenia: [PILOT_DATA_URODZENIA]<br>Adres: [PILOT_ADRES]<br>NIP: [PILOT_NIP]<br>e-mail: [PILOT_EMAIL], tel. [PILOT_TELEFON]<br>konto: [PILOT_KONTO_BANKOWE]<br>(„Przyjmujący zamówienie” / Pilot)</p>
<p>§1. Przedmiotem umowy jest sprawowanie opieki pilota podczas imprezy turystycznej <strong>[NAZWA_IMPREZY]</strong> w terminie [DATA_START] – [DATA_KONIEC].</p>
<p>§2. Wynagrodzenie (honorarium) wynosi: <strong>[PILOT_HONORARIUM]</strong>. Forma rozliczenia: [PILOT_FORMA_ROZLICZENIA].</p>
<p>§3. Pilot zobowiązuje się do należytego wykonania obowiązków pilota zgodnie z instrukcjami Zleceniodawcy oraz obowiązującymi przepisami.</p>
<p>§4. Umowę sporządzono w dwóch jednobrzmiących egzemplarzach.</p>
HTML;
    }

    protected function nextContractNumber(Event $event): string
    {
        $code = filled($event->code) ? Str::upper((string) $event->code) : (string) $event->id;

        return 'PILOT/'.$code.'/'.now()->format('Y');
    }

    protected function renderPdfBytes(
        string $contractNumber,
        string $bodyHtml,
        Event $event,
        Contractor $contractor,
        ContractorSettlementForm $form,
    ): string {
        // Tymczasowy obiekt tylko do widoku (bez zapisu).
        $stub = new EventPilotAgreement([
            'contract_number' => $contractNumber,
            'settlement_form' => $form,
        ]);

        $html = view('pdf.pilot-agreement', [
            'title' => $form->label().' — pilot',
            'agreement' => $stub,
            'bodyHtml' => $bodyHtml,
            'event' => $event,
            'contractor' => $contractor,
        ])->render();

        return DomPdfFactory::loadHTML($html)->output();
    }

    protected function storePdf(Event $event, string $bytes, ?string $previousPath): string
    {
        $directory = 'event-pilot-agreements/'.$event->id;
        $filename = 'umowa-'.now()->format('Ymd-His').'.pdf';
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $bytes);

        if (filled($previousPath) && $previousPath !== $path && Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }

    protected function syncPortalDocument(EventPilotAgreement $agreement): void
    {
        if (! Schema::hasTable('event_documents') || ! $agreement->hasPdf()) {
            return;
        }

        $agreement->loadMissing(['event', 'contractor']);
        $label = $agreement->settlementFormLabel().' — '.($agreement->contractor?->name ?? 'pilot');
        $bytes = $this->readPdfBytes($agreement);
        if ($bytes === null) {
            return;
        }

        $docPath = 'event-documents/'.$agreement->event_id.'/umowa-pilota-'.$agreement->id.'.pdf';
        Storage::disk('public')->put($docPath, $bytes);

        $document = $agreement->event_document_id
            ? EventDocument::query()->find($agreement->event_document_id)
            : null;

        if (! $document) {
            $document = new EventDocument([
                'event_id' => $agreement->event_id,
                'created_by' => Auth::id(),
            ]);
        }

        $document->fill([
            'name' => $label,
            'notes' => 'Wygenerowana umowa pilota '.$agreement->contract_number,
            'file_path' => $docPath,
            'original_filename' => $this->downloadFilename($agreement),
            'mime_type' => 'application/pdf',
            'file_size' => strlen($bytes),
            'attach_to_pilot_pdf' => true,
        ]);

        if (Schema::hasColumn('event_documents', 'approval_status')) {
            $document->approval_status = 'approved';
        }

        $document->save();

        if ((int) $agreement->event_document_id !== (int) $document->id) {
            $agreement->update(['event_document_id' => $document->id]);
        }
    }

    protected function detachPortalDocument(EventPilotAgreement $agreement): void
    {
        if (! $agreement->event_document_id || ! Schema::hasTable('event_documents')) {
            return;
        }

        $document = EventDocument::query()->find($agreement->event_document_id);
        if (! $document) {
            return;
        }

        // Odpinamy z panelu; nie kasujemy pliku umowy źródłowej.
        $document->update(['attach_to_pilot_pdf' => false]);
    }
}
