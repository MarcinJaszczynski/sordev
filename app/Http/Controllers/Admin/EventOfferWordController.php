<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocument;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use ZipArchive;

class EventOfferWordController extends Controller
{
    public function __invoke(Event $event)
    {
        $event->load([
            'startPlace',
            'eventTemplate',
            'qtyVariants',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'pricePerPerson.eventTemplateQty',
            'pricePerPerson.currency',
        ]);

        Settings::setCompatibility(true);
        if (class_exists('\\ZipArchive')) {
            Settings::setZipClass(Settings::ZIPARCHIVE);
        }

        $phpWord = new PhpWord;
        $phpWord->getSettings()->setThemeFontLang(
            (new Language('pl-PL'))->setLangId(1045)
        );
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $generatedAt = now();

        // --- SEKCJA 1: Pierwsza strona (wyśrodkowana pionowo za pomocą precyzyjnego odstępu góra) ---
        $firstPageSection = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);
        $this->configureWordSectionBranding($firstPageSection, $generatedAt);

        // 5000 twipów (~8.8 cm) spycha treść idealnie na środek pierwszej strony A4
        $this->addHeading($firstPageSection, 'OFERTA IMPREZY', 40, 'C00000', Jc::CENTER, 5000);

        $firstPageSection->addText($this->escapeText(trim((string) ($event->name ?: 'Impreza'))), [
            'bold' => true,
            'size' => 24,
            'color' => '0070C0',
        ], ['alignment' => Jc::CENTER]);

        $firstPageSection->addTextBreak(1);

        // Miejsce wyjazdu
        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText('Miejsce wyjazdu: ', ['bold' => true]);
        $run->addText($this->escapeText($event->startPlace?->name ?: 'do ustalenia'));

        // Termin
        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText('Termin: ', ['bold' => true]);
        $run->addText(($event->start_date ? $event->start_date->format('d.m.Y') : '—').' - '.($event->end_date ? $event->end_date->format('d.m.Y') : '—'));

        // Przewidywana liczba uczestników
        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText('Przewidywana liczba uczestników: ', ['bold' => true]);
        $run->addText((int) ($event->participant_count ?? 0) > 0 ? $event->participant_count : 'do ustalenia');

        // Zamawiający przywołanie danych
        $clientName  = trim((string) ($event->client_name ?? ''));
        $clientEmail = trim((string) ($event->client_email ?? ''));
        $clientPhone = trim((string) ($event->client_phone ?? ''));

        $firstPageSection->addTextBreak(1);

        $orgRun = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $orgRun->addText('Organizator: ', ['bold' => true]);
        $orgRun->addText('Biuro Podróży RAFA');

        $firstPageSection->addText('ul. Marii Konopnickiej 6, 00-491 Warszawa', [], ['alignment' => Jc::CENTER]);
        $firstPageSection->addText('tel. +48 606 102 243', [], ['alignment' => Jc::CENTER]);

        // Zamawiający
        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText('Zamawiający: ', ['bold' => true]);
        $run->addText($this->escapeText($clientName !== '' ? $clientName : '—'));

        if ($clientPhone !== '') {
            $phoneRun = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
            $phoneRun->addText('tel. '.$this->escapeText($clientPhone));
        }

        if ($clientEmail !== '') {
            $emailRun = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
            $emailRun->addText($this->escapeText($clientEmail));
        }

        //Transport przywołanie danych

        $transportCompany  = trim((string) ($event->transport_company_name ?? ''));
        $busInfo           = trim((string) ($event->bus_info ?? ''));
        $pickupDetails     = trim((string) ($event->pickup_place_details ?? ''));
        $transportLine     = $transportCompany ?: '—';
        if ($busInfo !== '') {
            $transportLine .= ' ('.$busInfo.')';
        }

        $firstPageSection->addTextBreak(1);

        // Transport
        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText('Transport: ', ['bold' => true]);
        $run->addText($this->escapeText($transportLine));

        // Miejsce zbiórki
        if ($pickupDetails !== '') {
            $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
            $run->addText('Miejsce zbiórki: ', ['bold' => true]);
            $run->addText($this->escapeText($pickupDetails));
        }

        // --- SEKCJA 2: Pozostałe strony (standardowe wyrównanie od góry) ---
        $normalSection = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);
        $this->configureWordSectionBranding($normalSection, $generatedAt);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'PROGRAM', 18, 'C00000');
        $normalSection->addTextBreak(1);
        $programPoints = $event->programPoints
            ->where('include_in_program', true)
            ->where('active', true)
            ->sortBy([
                ['day', 'asc'],
                ['order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($programPoints->isEmpty()) {
            $normalSection->addText('Program szczegółowy przygotujemy pod wymagania grupy.');
        } else {
            $byDay = $programPoints->groupBy(fn ($point) => (int) ($point->day ?? 1))->sortKeys();
            foreach ($byDay as $day => $points) {
                $normalSection->addText('Dzień '.$day, ['bold' => true, 'color' => '0070C0']);
                foreach ($points as $point) {
                    $line = $this->escapeText(trim((string) $point->name));
                    $desc = trim((string) ($point->description ?? ''));
                    if ($desc !== '') {
                        $line .= ' - '.$this->stripHtmlToSentence($desc);
                    }
                    $normalSection->addListItem($line, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
                }
                $normalSection->addTextBreak(1);
            }
        }

        $this->addDivider($normalSection);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENNIK', 18, 'C00000');
        $normalSection->addTextBreak(1);
        $priceRanges = $this->buildEventPriceRanges($event);

        if (empty($priceRanges)) {
            $normalSection->addText('Cennik zostanie przedstawiony po doprecyzowaniu liczby uczestników.');
        } else {
            foreach ($priceRanges as $row) {
                $normalSection->addText(
                    number_format((float) $row['price'], 0, ',', ' ').' PLN za osobę',
                    ['bold' => true, 'size' => 13]
                );
                $normalSection->addText('cena dla grupy '.$row['label']);
                $normalSection->addTextBreak(1);
            }
        }

        $normalSection->addText('Zapytaj o ofertę dla innej ilości osób.', ['italic' => true, 'color' => '0070C0']);

        $this->addDivider($normalSection);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        $defaultIncludes = [
            'realizację programu wycieczki',
            'wyżywienie zgodnie z programem',
            'przejazd autokarem',
            'opłaty drogowe i parkingowe',
            'przewodników lokalnych',
            'opiekę pilota i organizację logistyczną',
            'ubezpieczenie zgodnie z charakterem imprezy',
            'podatek VAT',

        ];

        foreach ($defaultIncludes as $item) {
            $normalSection->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA NIE ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        $defaultExcludes = [
            'wydatków własnych uczestników',
            'punktów programu opisanych jako fakultatywne',
            'świadczeń nieujętych wyraźnie w sekcji „Co zawiera cena”',
        ];
        foreach ($defaultExcludes as $item) {
            $normalSection->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $this->addDivider($normalSection);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'WARUNKI OFERTY', 18, 'C00000');
        $normalSection->addTextBreak(1);
        $offerTerms = [
            'Program ma charakter ramowy, a kolejność realizacji punktów może ulec zmianie z przyczyn organizacyjnych.',
            'Kalkulacja ceny opiera się o aktualne stawki świadczeń i może wymagać aktualizacji przy istotnej zmianie kosztów.',
            'Cena za osobę zależy od ostatecznej liczby uczestników i obowiązuje dla wskazanych progów ilościowych.',
            'Ostateczne potwierdzenie świadczeń następuje po akceptacji oferty i podpisaniu umowy.',
            'Oferta jest ważna przez 7 dni od daty wygenerowania dokumentu, chyba że ustalono inaczej.',
        ];
        foreach ($offerTerms as $item) {
            $normalSection->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $normalSection->addTextBreak(1);
        $normalSection->addText('Dokument wygenerowany: '.$generatedAt->format('d.m.Y H:i'), ['size' => 9, 'color' => '777777']);

        $fileName = 'oferta-imprezy-'.$event->id.'-'.$generatedAt->format('Ymd-His').'.docx';
        $relativePath = 'event-offers/'.$fileName;
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $this->injectLogoIntoDocx($absolutePath);

        $disk = Storage::disk('public');
        EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Oferta imprezy - '.($event->name ?: ('Event #'.$event->id)),
            'notes' => 'Oferta wygenerowana automatycznie z dashboardu.',
            'file_path' => $relativePath,
            'original_filename' => $fileName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => $disk->exists($relativePath) ? $disk->size($relativePath) : null,
            'attach_to_folder_pdf' => true,
            'approval_status' => 'approved',
            'is_offer' => true,
            'offer_status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return response()->download(
            $absolutePath,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(false);
    }

    protected function addHeading($container, string $text, int $size, string $color, string $alignment = 'left', int $spaceBefore = 0): void
    {
        $container->addText($text, [
            'bold' => true,
            'size' => $size,
            'color' => $color,
        ], [
            'alignment' => $alignment,
            'spaceBefore' => $spaceBefore,
        ]);
    }

    protected function configureWordSectionBranding($section, $generatedAt): void
    {
        $header = $section->addHeader();

        $logoPath = public_path('uploads/logo.png');
        if (file_exists($logoPath)) {
            [$imgW, $imgH] = @getimagesize($logoPath) ?: [300, 76];
            $displayW = 90;
            $displayH = (int) round($imgH * ($displayW / max(1, $imgW)));
            $header->addImage($logoPath, [
                'width'    => $displayW,
                'height'    => $displayH,
                'alignment' => Jc::CENTER,
            ]);
        }

        $footer = $section->addFooter();
        $table = $footer->addTable([
            'unit' => 'dxa',
            'alignment' => JcTable::CENTER,
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ]);
        $table->addRow();

        $colWidth = 3301;

        $cell1 = $table->addCell($colWidth);
        $cell1->addText('Biuro Podróży RAFA', ['size' => 8, 'color' => '666666'], ['alignment' => 'left', 'spaceAfter' => 0]);
        $cell1->addText('ul. Marii Konopnickiej 6', ['size' => 8, 'color' => '666666'], ['alignment' => 'left', 'spaceAfter' => 0]);
        $cell1->addText('00-491 Warszawa', ['size' => 8, 'color' => '666666'], ['alignment' => 'left', 'spaceAfter' => 0]);

        $cell2 = $table->addCell($colWidth);
        $cell2->addText('NIP 716-250-87-61', ['size' => 8, 'color' => '666666'], ['alignment' => 'center', 'spaceAfter' => 0]);
        $cell2->addText('Bank Millenium S.A', ['size' => 8, 'color' => '666666'], ['alignment' => 'center', 'spaceAfter' => 0]);
        $cell2->addText('10 1160 2202 0000 0002 0065 6958', ['size' => 8, 'color' => '666666'], ['alignment' => 'center', 'spaceAfter' => 0]);

        $cell3 = $table->addCell($colWidth);
        $cell3->addText('tel. +48 606 102 243', ['size' => 8, 'color' => '666666'], ['alignment' => 'right', 'spaceAfter' => 0]);
        $cell3->addText('www.bprafa.pl', ['size' => 8, 'color' => '666666'], ['alignment' => 'right', 'spaceAfter' => 0]);
        $cell3->addText('rafa@bprafa.pl', ['size' => 8, 'color' => '666666'], ['alignment' => 'right', 'spaceAfter' => 0]);
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

        if (! preg_match('/r:id=["\'](rId\d+)["\']/', $headerXml, $m)) {
            $zip->close();
            return;
        }
        $rId = $m[1];

        [$imgW, $imgH] = @getimagesize($logoPath) ?: [300, 76];
        $displayW   = 90;
        $displayH   = (int) round($imgH * ($displayW / max(1, $imgW)));
        $widthEmu   = $displayW * 12700;
        $heightEmu  = $displayH * 12700;

        $drawingMl = '<w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $widthEmu . '" cy="' . $heightEmu . '"/>'
            . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:docPr id="1" name="Logo" descr="Biuro Podróży RAFA"/>'
            . '<wp:cNvGraphicFramePr>'
            .   '<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            . '</wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .   '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .     '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .       '<pic:nvPicPr>'
            .         '<pic:cNvPr id="0" name="logo.png"/>'
            .         '<pic:cNvPicPr/>'
            .       '</pic:nvPicPr>'
            .       '<pic:blipFill>'
            .         '<a:blip r:embed="' . $rId . '"/>'
            .         '<a:stretch><a:fillRect/></a:stretch>'
            .       '</pic:blipFill>'
            . '<pic:spPr>'
            .   '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/></a:xfrm>'
            .   '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            .   '<a:ln><a:noFill/></a:ln>'
            . '</pic:spPr>'
            .     '</pic:pic>'
            .   '</a:graphicData>'
            . '</a:graphic>'
            . '</wp:inline>'
            . '</w:drawing>';

        $newHeaderXml = preg_replace(
            '/<w:pict>.*?<\/w:pict>/s',
            $drawingMl,
            $headerXml
        );

        if ($newHeaderXml !== null && $newHeaderXml !== $headerXml) {
            $zip->addFromString('word/header1.xml', $newHeaderXml);
        }

        $zip->close();
    }

    protected function addMultilineText($section, string $text): void
    {
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($this->stripHtmlToSentence($line));
            if ($line === '') {
                continue;
            }
            $section->addText($line);
        }
    }

    protected function stripHtmlToSentence(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $this->escapeText($text);
    }

    protected function escapeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8', false);
    }

    protected function buildEventPriceRanges(Event $event): array
    {
        // Standard offer tiers: label shown in the document, qty = lookup key in DB / kalkulator.
        $offerTiers = [
            ['qty' => 20, 'label' => '20-24 + 2 opiekunów gratis'],
            ['qty' => 25, 'label' => '25-29 + 2 opiekunów gratis'],
            ['qty' => 30, 'label' => '30-34 + 3 opiekunów gratis'],
            ['qty' => 35, 'label' => '35-39 + 3 opiekunów gratis'],
            ['qty' => 40, 'label' => '40-55 + 4 opiekunów gratis'],
        ];

        $pricesByQty = $this->collectEventPricesByQty($event);

        // Uzupełnij brakujące progi cenami ze szablonu (dokładne qty).
        foreach ($offerTiers as $tier) {
            $qty = $tier['qty'];
            if (($pricesByQty[$qty] ?? 0) > 0) {
                continue;
            }
            $templatePrice = $this->lookupTemplatePriceForQty($event, $qty);
            if ($templatePrice > 0) {
                $pricesByQty[$qty] = $templatePrice;
            }
        }

        // Uzupełnij pozostałe progi kalkulatorem (gdy brak wiersza z qty w DB).
        foreach ($offerTiers as $tier) {
            $qty = $tier['qty'];
            if (($pricesByQty[$qty] ?? 0) > 0) {
                continue;
            }
            $calculated = $this->calculatePriceForQty($event, $qty);
            if ($calculated > 0) {
                $pricesByQty[$qty] = $calculated;
            }
        }

        $ranges = [];
        foreach ($offerTiers as $tier) {
            $price = (float) ($pricesByQty[$tier['qty']] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $ranges[] = [
                'label' => $tier['label'],
                'price' => (int) ceil($price / 5) * 5,
            ];
        }

        return $ranges;
    }

    /**
     * Ceny z event_price_per_person zmapowane po dokładnym qty (PLN).
     *
     * @return array<int, float>
     */
    protected function collectEventPricesByQty(Event $event): array
    {
        $prices = [];
        $ids = [];

        foreach ($event->pricePerPerson as $row) {
            $qty = (int) ($row->eventTemplateQty->qty ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $price = (float) ($row->price_per_person ?? 0);
            if ($price <= 0) {
                continue;
            }

            // Brak currency_id = PLN (tak zapisuje silnik kalkulacji).
            $currency = strtoupper((string) ($row->currency?->symbol ?? $row->currency?->code ?? 'PLN'));
            if ($currency !== 'PLN') {
                continue;
            }

            if ($event->start_place_id
                && (int) ($row->start_place_id ?? 0) !== (int) $event->start_place_id
            ) {
                continue;
            }

            // Najnowszy wiersz wygrywa przy duplikatach.
            if (! isset($ids[$qty]) || (int) $row->id >= $ids[$qty]) {
                $prices[$qty] = $price;
                $ids[$qty] = (int) $row->id;
            }
        }

        return $prices;
    }

    protected function lookupTemplatePriceForQty(Event $event, int $qty): float
    {
        if (! $event->event_template_id || $qty <= 0) {
            return 0.0;
        }

        $qtyId = \App\Models\EventTemplateQty::query()
            ->where('qty', $qty)
            ->value('id');

        if (! $qtyId) {
            return 0.0;
        }

        $query = \App\Models\EventTemplatePricePerPerson::query()
            ->with('currency')
            ->where('event_template_id', $event->event_template_id)
            ->where('event_template_qty_id', $qtyId)
            ->where('price_per_person', '>', 0);

        if ($event->start_place_id) {
            $query->where('start_place_id', $event->start_place_id);
        }

        $rows = $query->orderByDesc('id')->get();

        foreach ($rows as $row) {
            $currency = strtoupper((string) ($row->currency?->symbol ?? $row->currency?->code ?? 'PLN'));
            if ($currency !== 'PLN') {
                continue;
            }

            return (float) $row->price_per_person;
        }

        return 0.0;
    }

    protected function calculatePriceForQty(Event $event, int $qty): float
    {
        if (! $event->eventTemplate || ! $event->start_place_id || $qty <= 0) {
            return 0.0;
        }

        try {
            $gratis = $event->resolveGratisCountForParticipantCount($qty);
            $engine = new \App\Services\EventTemplateCalculationEngine;
            $result = $engine->calculateDetailedForCustomGroup(
                $event->eventTemplate,
                $qty,
                $gratis,
                (int) $event->start_place_id,
                null,
                false
            );

            $price = (float) ($result['price_per_person'] ?? 0);
            if ($price > 0) {
                return $price;
            }
        } catch (\Throwable) {
            // ignore — brak cennika dla tego progu
        }

        return 0.0;
    }

    protected function addDivider($section): void
    {
        $section->addText('', [], [
            'borderBottomSize' => 6,
            'borderBottomColor' => 'CCCCCC',
            'borderBottomStyle' => 'single',
            'spaceBefore' => 120,
            'spaceAfter' => 120,
        ]);
    }
}
