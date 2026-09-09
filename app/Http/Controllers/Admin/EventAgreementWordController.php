<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocument;
use App\Services\Documents\EventAgreementWordPayloadResolver;
use App\Services\Documents\WordAgreementContent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;
use ZipArchive;

/**
 * Generuje DOCX „Potwierdzenie zawarcia umowy…” z poziomu imprezy (wzór PDF RAFA).
 */
class EventAgreementWordController extends Controller
{
    public function __construct(
        private readonly WordAgreementContent $content,
        private readonly EventAgreementWordPayloadResolver $payloadResolver,
    ) {}

    public function __invoke(Event $event)
    {
        Gate::authorize('view', $event);

        $event->load([
            'startPlace',
            'eventTemplate.eventTypes',
            'eventTemplate.eventPriceDescription',
            'hotelStays.contractor',
            'orderingContractors',
            'paymentInstallmentTemplates',
            'agreements.paymentSchedules',
        ]);

        Settings::setCompatibility(true);
        if (class_exists(ZipArchive::class)) {
            Settings::setZipClass(Settings::ZIPARCHIVE);
        }

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(
            (new Language('pl-PL'))->setLangId(1045)
        );
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $sectionOptions = [
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1000,
            'marginBottom' => 1200,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ];

        $payload = $this->payloadResolver->resolve($event);
        $section = $phpWord->addSection($sectionOptions);
        $this->configureWordSectionBranding($section);

        $this->buildConfirmationSection($section, $payload);
        $this->buildTermsSection($section);
        $this->buildStandardFormSection($section);

        $generatedAt = now();
        $fileName = 'umowa-imprezy-'.$event->id.'-'.$generatedAt->format('Ymd-His').'.docx';
        $relativePath = 'event-agreements/'.$fileName;
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
        $this->injectLogoIntoDocx($absolutePath);

        $disk = Storage::disk('public');
        EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Umowa — '.($event->name ?: ('Event #'.$event->id)),
            'notes' => 'Potwierdzenie zawarcia umowy wygenerowane automatycznie z dashboardu.',
            'file_path' => $relativePath,
            'original_filename' => $fileName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => $disk->exists($relativePath) ? $disk->size($relativePath) : null,
            'attach_to_folder_pdf' => false,
            'is_offer' => false,
            'approval_status' => 'approved',
            'created_by' => auth()->id(),
        ]);

        return response()->download(
            $absolutePath,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(false);
    }

    /**
     * @param  \PhpOffice\PhpWord\Element\Section  $section
     * @param  array<string, mixed>  $payload
     */
    protected function buildConfirmationSection($section, array $payload): void
    {
        $titleStyle = ['bold' => true, 'size' => 16];
        $headingStyle = ['bold' => true, 'size' => 12];
        $bodyStyle = ['size' => 11];
        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 120];
        $para = ['spaceBefore' => 40, 'spaceAfter' => 40, 'alignment' => Jc::BOTH];
        $paraLeft = ['spaceBefore' => 40, 'spaceAfter' => 40];

        $section->addText(
            $this->escapeText('Potwierdzenie zawarcia umowy o organizację imprezy turystycznej nr '.$payload['contract_number']),
            $titleStyle,
            $center
        );

        $section->addText(
            $this->escapeText('Umowa zawarta dnia '.$payload['agreement_date'].' pomiędzy:'),
            $bodyStyle,
            $paraLeft
        );

        $section->addText('zamawiającym:', ['bold' => true, 'size' => 11], $paraLeft);
        foreach ($payload['ordering_party_lines'] as $line) {
            $section->addText($this->escapeText((string) $line), $bodyStyle, $paraLeft);
        }

        $section->addText('a organizatorem:', ['bold' => true, 'size' => 11], [
            'spaceBefore' => 120,
            'spaceAfter' => 40,
        ]);
        foreach ($payload['organizer_lines'] as $line) {
            $section->addText($this->escapeText((string) $line), $bodyStyle, $paraLeft);
        }

        $section->addText('Informacje o imprezie turystycznej', $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);

        $this->addLabelValue($section, 'Rodzaj imprezy turystycznej:', (string) $payload['event_type'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Nazwa imprezy/destynacja:', (string) $payload['event_name'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Termin wycieczki:', (string) $payload['trip_dates'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Środek transportu:', (string) $payload['transport'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Ilość uczestników (łącznie z opiekunami):', (string) $payload['participant_total'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Ilość opiekunów:', (string) $payload['guardians_count'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Wyjazd:', (string) $payload['departure_line'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Powrót:', (string) $payload['return_line'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Obiekt noclegowy:', (string) $payload['hotel'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Wyżywienie:', (string) $payload['meals'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Ubezpieczenie:', (string) $payload['insurance'], $bodyStyle, $para);
        $this->addLabelValue($section, 'Dodatkowe informacje:', (string) $payload['additional_info'], $bodyStyle, $para);

        $section->addText('Podstawienie i miejsce zbiórki', $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        $this->addLabelValue($section, 'Miejsce podstawienia autokaru:', (string) $payload['pickup_place'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Godzina podstawienia:', (string) $payload['pickup_time'], $bodyStyle, $paraLeft);

        $section->addText(
            $this->escapeText('Uwaga! Autokar zostanie podstawiony w miejscu wskazanym w umowie. Zamawiający lub Podróżny mają prawo do poproszenia odpowiednich służb o przeprowadzenie kontroli stanu technicznego autokaru oraz trzeźwości kierowcy. Kontrola taka odbywa się w miejscu podstawienia autokaru na 30 minut przed planowaną godziną wyjazdu. Wszelkie formalności związane z wezwaniem służb uprawnionych do kontroli pozostają po stronie Zamawiającego/Podróżnego zlecającego kontrolę.'),
            $bodyStyle,
            $para
        );

        $section->addText('Cena imprezy i harmonogram wpłat', $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        $this->addLabelValue($section, 'Cena brutto:', (string) $payload['gross_price_line'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Słownie:', (string) $payload['amount_in_words'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Sposób płatności:', (string) $payload['payment_method'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Cena obejmuje:', (string) $payload['price_includes'], $bodyStyle, $para);

        $section->addText('Terminy rozliczenia harmonogramu wpłat:', ['bold' => true, 'size' => 11], [
            'spaceBefore' => 120,
            'spaceAfter' => 40,
        ]);
        foreach ($payload['payment_schedule_lines'] as $line) {
            $section->addText($this->escapeText((string) $line), $bodyStyle, $paraLeft);
        }

        $section->addText($this->escapeText((string) $payload['bank_account_line']), $bodyStyle, [
            'spaceBefore' => 120,
            'spaceAfter' => 40,
        ]);
        $section->addText($this->escapeText((string) $payload['transfer_description']), $bodyStyle, $para);

        $section->addText(
            $this->escapeText('Wpłata zaliczki na konto Biura Podróży RAFA jest jednoznaczna z zawarciem umowy oraz akceptacją warunków uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA. Umowa zostaje zawarta w chwili zaksięgowania zapłaty na rachunku Biura Podróży RAFA'),
            $bodyStyle,
            $para
        );
        $section->addText(
            $this->escapeText('Brak zapłaty w wyznaczonym terminie jest jednoznaczny z rezygnacją przez Zamawiającego z organizacji imprezy turystycznej.'),
            $bodyStyle,
            $para
        );

        $section->addText('Załączniki do umowy', $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        foreach (WordAgreementContent::ATTACHMENTS as $attachment) {
            $section->addText($this->escapeText($attachment), $bodyStyle, $paraLeft);
        }
    }

    /**
     * @param  \PhpOffice\PhpWord\Element\Section  $section
     */
    protected function buildTermsSection($section): void
    {
        $section->addPageBreak();

        $bodyStyle = ['size' => 10];
        $para = ['spaceBefore' => 40, 'spaceAfter' => 40, 'alignment' => Jc::BOTH];
        $paraLeft = ['spaceBefore' => 40, 'spaceAfter' => 40];
        $subPara = ['spaceBefore' => 20, 'spaceAfter' => 20, 'indentation' => ['left' => 360]];

        foreach ($this->content->termsOfParticipationBlocks() as $block) {
            $text = $this->escapeText($block['text']);
            match ($block['type']) {
                'title' => $section->addText($text, ['bold' => true, 'size' => 13], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => 80,
                    'spaceAfter' => 160,
                ]),
                'heading' => $section->addText($text, ['bold' => true, 'size' => 11], [
                    'spaceBefore' => 160,
                    'spaceAfter' => 60,
                ]),
                'sub' => $section->addText($text, $bodyStyle, $subPara),
                default => $section->addText($text, $bodyStyle, $para),
            };
        }
    }

    /**
     * @param  \PhpOffice\PhpWord\Element\Section  $section
     */
    protected function buildStandardFormSection($section): void
    {
        $section->addPageBreak();

        $bodyStyle = ['size' => 10];
        $para = ['spaceBefore' => 60, 'spaceAfter' => 60, 'alignment' => Jc::BOTH];

        foreach ($this->content->standardInformationFormBlocks() as $block) {
            $text = $this->escapeText($block['text']);
            match ($block['type']) {
                'title' => $section->addText($text, ['bold' => true, 'size' => 13], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => 80,
                    'spaceAfter' => 160,
                ]),
                'link' => $section->addText($text, ['size' => 9, 'color' => '0563C1'], $para),
                default => $section->addText($text, $bodyStyle, $para),
            };
        }
    }

    /**
     * @param  \PhpOffice\PhpWord\Element\Section  $section
     * @param  array<string, mixed>  $font
     * @param  array<string, mixed>  $para
     */
    protected function addLabelValue($section, string $label, string $value, array $font, array $para): void
    {
        $run = $section->addTextRun($para);
        $run->addText($this->escapeText($label).' ', ['bold' => true, 'size' => $font['size'] ?? 11]);
        $run->addText($this->escapeText($value), $font);
    }

    protected function configureWordSectionBranding($section): void
    {
        $header = $section->addHeader();
        $logoPath = public_path('uploads/logo.png');
        if (file_exists($logoPath)) {
            [$imgW, $imgH] = @getimagesize($logoPath) ?: [300, 76];
            $displayW = 90;
            $displayH = (int) round($imgH * ($displayW / max(1, $imgW)));
            $header->addImage($logoPath, [
                'width' => $displayW,
                'height' => $displayH,
                'alignment' => Jc::CENTER,
                'wrappingStyle' => 'inline',
            ]);
        }

        $footer = $section->addFooter();
        $contentWidth = 9638;
        $centerTab = (int) round($contentWidth / 2);
        $rightTab = $contentWidth;
        $footerPara = [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'tabs' => [
                new \PhpOffice\PhpWord\Style\Tab('center', $centerTab),
                new \PhpOffice\PhpWord\Style\Tab('right', $rightTab),
            ],
        ];
        $footerFont = ['size' => 8, 'color' => '666666'];
        $tab = "\t";

        $footer->addText(
            'Biuro Podróży RAFA'.$tab.'NIP 716-250-87-61'.$tab.'tel. +48 606 102 243',
            $footerFont,
            $footerPara
        );
        $footer->addText(
            'ul. Marii Konopnickiej 6'.$tab.'Bank Millenium S.A'.$tab.'www.bprafa.pl',
            $footerFont,
            $footerPara
        );
        $footer->addText(
            '00-491 Warszawa'.$tab.'10 1160 2202 0000 0002 0065 6958'.$tab.'rafa@bprafa.pl',
            $footerFont,
            $footerPara
        );
    }

    protected function injectLogoIntoDocx(string $docxPath): void
    {
        $logoPath = public_path('uploads/logo.png');
        if (! file_exists($logoPath)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxPath, ZipArchive::CREATE) !== true) {
            return;
        }

        $headerXml = $zip->getFromName('word/header1.xml');
        if ($headerXml === false) {
            $zip->close();

            return;
        }

        if (! str_contains($headerXml, '<w:pict>') && ! str_contains($headerXml, '<v:shape>')) {
            $zip->close();

            return;
        }

        // Logo już osadzone przez PhpWord — nic do poprawki.
        $zip->close();
    }

    protected function escapeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u', '', $text) ?? $text;

        return trim($text);
    }
}
