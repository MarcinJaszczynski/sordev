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
use PhpOffice\PhpWord\Style\ListItem;
use ZipArchive;

/**
 * Generuje DOCX „Potwierdzenie zawarcia umowy…” z poziomu imprezy (wzór PDF RAFA).
 *
 * Kompatybilność Word / LibreOffice:
 * - escapowanie XML (bare `&` w URL SFI psuje document.xml — Pages to łyknie, MS Word nie),
 * - A4 / marginesy jako int (float z defaultów PhpWord psuje LO),
 * - logo w nagłówku jako DrawingML zamiast VML.
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
        Settings::setOutputEscapingEnabled(true);
        if (class_exists(ZipArchive::class)) {
            Settings::setZipClass(Settings::ZIPARCHIVE);
        }

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(
            (new Language('pl-PL'))->setLangId(1045)
        );
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        // A4 w twipach jako int — float z defaultów PhpWord psuje LibreOffice / doxswap.
        // Marginesy ~2 cm jak we wzorze PDF.
        $sectionOptions = [
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1134,
            'marginBottom' => 1134,
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
        $titleStyle = ['bold' => true, 'size' => 14];
        $headingStyle = ['bold' => true, 'size' => 11];
        $bodyStyle = ['size' => 10];
        $labelStyle = ['bold' => true, 'size' => 10];
        $center = ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 160];
        $para = ['spaceBefore' => 40, 'spaceAfter' => 60, 'alignment' => Jc::BOTH, 'lineHeight' => 1.15];
        $paraLeft = ['spaceBefore' => 20, 'spaceAfter' => 40, 'lineHeight' => 1.15];
        $tightLeft = ['spaceBefore' => 0, 'spaceAfter' => 20, 'lineHeight' => 1.1];

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

        $section->addText($this->escapeText('zamawiającym:'), $labelStyle, [
            'spaceBefore' => 80,
            'spaceAfter' => 20,
        ]);
        foreach ($payload['ordering_party_lines'] as $line) {
            $section->addText($this->escapeText((string) $line), $bodyStyle, $tightLeft);
        }

        $section->addText($this->escapeText('a organizatorem:'), $labelStyle, [
            'spaceBefore' => 120,
            'spaceAfter' => 20,
        ]);
        foreach ($payload['organizer_lines'] as $line) {
            $section->addText($this->escapeText((string) $line), $bodyStyle, $tightLeft);
        }

        $section->addText($this->escapeText('Informacje o imprezie turystycznej'), $headingStyle, [
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

        $section->addText($this->escapeText('Podstawienie i miejsce zbiórki'), $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        $this->addLabelValue($section, 'Miejsce podstawienia autokaru:', (string) $payload['pickup_place'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Godzina podstawienia:', (string) $payload['pickup_time'], $bodyStyle, $paraLeft);

        $warningRun = $section->addTextRun($para);
        $warningRun->addText($this->escapeText('Uwaga! '), ['bold' => true, 'size' => 10]);
        $warningRun->addText(
            $this->escapeText('Autokar zostanie podstawiony w miejscu wskazanym w umowie. Zamawiający lub Podróżny mają prawo do poproszenia odpowiednich służb o przeprowadzenie kontroli stanu technicznego autokaru oraz trzeźwości kierowcy. Kontrola taka odbywa się w miejscu podstawienia autokaru na 30 minut przed planowaną godziną wyjazdu. Wszelkie formalności związane z wezwaniem służb uprawnionych do kontroli pozostają po stronie Zamawiającego/Podróżnego zlecającego kontrolę.'),
            $bodyStyle
        );

        $section->addText($this->escapeText('Cena imprezy i harmonogram wpłat'), $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        $this->addLabelValue($section, 'Cena brutto:', (string) $payload['gross_price_line'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Słownie:', (string) $payload['amount_in_words'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Sposób płatności:', (string) $payload['payment_method'], $bodyStyle, $paraLeft);
        $this->addLabelValue($section, 'Cena obejmuje:', (string) $payload['price_includes'], $bodyStyle, $para);

        $section->addText($this->escapeText('Terminy rozliczenia harmonogramu wpłat:'), $labelStyle, [
            'spaceBefore' => 120,
            'spaceAfter' => 40,
        ]);
        foreach ($payload['payment_schedule_lines'] as $line) {
            $section->addText(
                $this->escapeText((string) $line),
                $bodyStyle,
                ['spaceBefore' => 0, 'spaceAfter' => 40, 'indentation' => ['left' => 200]]
            );
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

        $section->addText($this->escapeText('Załączniki do umowy'), $headingStyle, [
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        foreach (WordAgreementContent::ATTACHMENTS as $attachment) {
            $section->addText(
                $this->escapeText($attachment),
                $bodyStyle,
                ['spaceBefore' => 0, 'spaceAfter' => 40, 'indentation' => ['left' => 200]]
            );
        }
    }

    /**
     * @param  \PhpOffice\PhpWord\Element\Section  $section
     */
    protected function buildTermsSection($section): void
    {
        // Jak w PDF: OWU płynie zaraz po załącznikach (bez sztucznego page break).
        $bodyStyle = ['size' => 9];
        $para = ['spaceBefore' => 20, 'spaceAfter' => 40, 'alignment' => Jc::BOTH, 'lineHeight' => 1.1];
        $subPara = [
            'spaceBefore' => 0,
            'spaceAfter' => 20,
            'alignment' => Jc::BOTH,
            'indentation' => ['left' => 360, 'hanging' => 180],
            'lineHeight' => 1.1,
        ];

        foreach ($this->content->termsOfParticipationBlocks() as $block) {
            $text = $this->escapeText($block['text']);
            match ($block['type']) {
                'title' => $section->addText($text, ['bold' => true, 'size' => 12], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => 240,
                    'spaceAfter' => 160,
                ]),
                'heading' => $section->addText($text, ['bold' => true, 'size' => 10], [
                    'spaceBefore' => 140,
                    'spaceAfter' => 40,
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
        // Jak w PDF: SFI zaraz po OWU, bez osobnej strony.
        $bodyStyle = ['size' => 9];
        $para = ['spaceBefore' => 40, 'spaceAfter' => 60, 'alignment' => Jc::BOTH, 'lineHeight' => 1.15];
        $listStyle = ['listType' => ListItem::TYPE_BULLET_FILLED];
        $listPara = ['spaceBefore' => 20, 'spaceAfter' => 40, 'alignment' => Jc::BOTH, 'lineHeight' => 1.15];

        foreach ($this->content->standardInformationFormBlocks() as $block) {
            $text = $this->escapeText($block['text']);
            match ($block['type']) {
                'title' => $section->addText($text, ['bold' => true, 'size' => 12], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => 240,
                    'spaceAfter' => 160,
                ]),
                'bullet' => $section->addListItem($text, 0, $bodyStyle, $listStyle, $listPara),
                'link' => $section->addLink($text, $text, ['size' => 8, 'color' => '0563C1', 'underline' => 'single'], $para),
                'footer' => $section->addText($text, ['bold' => true, 'size' => 9, 'italic' => true], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => 200,
                    'spaceAfter' => 40,
                ]),
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
        $run->addText($this->escapeText($label).' ', ['bold' => true, 'size' => $font['size'] ?? 10]);
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

    /**
     * PhpWord osadza logo jako VML (&lt;w:pict&gt;) — Word Desktop / LibreOffice często tego nie łykają.
     * Podmieniamy na DrawingML (jak w Oferta Word).
     */
    protected function injectLogoIntoDocx(string $docxPath): void
    {
        $logoPath = public_path('uploads/logo.png');
        if (! file_exists($logoPath)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        [$imgW, $imgH] = @getimagesize($logoPath) ?: [300, 76];
        $displayW = 90;
        $displayH = (int) round($imgH * ($displayW / max(1, $imgW)));
        $widthEmu = (int) round($displayW * 9525);
        $heightEmu = (int) round($displayH * 9525);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^word/header\d+\.xml$#', $name)) {
                continue;
            }

            $headerXml = $zip->getFromIndex($i);
            if ($headerXml === false) {
                continue;
            }

            if (! str_contains($headerXml, '<w:pict>') && ! str_contains($headerXml, '<v:shape>')) {
                continue;
            }

            if (! preg_match('/r:id=["\'](rId\d+)["\']/', $headerXml, $m)) {
                continue;
            }

            $rId = $m[1];
            $drawingMl = '<w:drawing>'
                .'<wp:inline distT="0" distB="0" distL="0" distR="0">'
                .'<wp:extent cx="'.$widthEmu.'" cy="'.$heightEmu.'"/>'
                .'<wp:docPr id="1" name="logo"/>'
                .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                .'<pic:nvPicPr><pic:cNvPr id="0" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
                .'<pic:blipFill>'
                .'<a:blip r:embed="'.$rId.'"/>'
                .'<a:stretch><a:fillRect/></a:stretch>'
                .'</pic:blipFill>'
                .'<pic:spPr>'
                .'<a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$widthEmu.'" cy="'.$heightEmu.'"/></a:xfrm>'
                .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
                .'<a:ln><a:noFill/></a:ln>'
                .'</pic:spPr>'
                .'</pic:pic>'
                .'</a:graphicData>'
                .'</a:graphic>'
                .'</wp:inline>'
                .'</w:drawing>';

            $newHeaderXml = preg_replace('/<w:pict>.*?<\/w:pict>/s', $drawingMl, $headerXml);
            if ($newHeaderXml !== null && $newHeaderXml !== $headerXml) {
                $zip->addFromString($name, $newHeaderXml);
            }
        }

        $zip->close();
    }

    protected function escapeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

        return trim($text);
    }
}
