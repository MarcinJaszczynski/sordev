<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use ZipArchive;

class EventOfferWordController extends Controller
{
    public function __invoke(Event $event)
    {
        Gate::authorize('view', $event);

        $event->load([
            'startPlace',
            'eventTemplate.eventPriceDescription',
            'qtyVariants',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'pricePerPerson.eventTemplateQty',
            'pricePerPerson.currency',
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

        $generatedAt = now();

        // A4 w twipach jako int — float z defaultów PhpWord psuje LibreOffice / doxswap.
        $sectionOptions = [
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ];

        // --- SEKCJA 1: Okładka ---
        $firstPageSection = $phpWord->addSection($sectionOptions);
        $this->configureWordSectionBranding($firstPageSection);

        $this->addHeading($firstPageSection, 'OFERTA IMPREZY', 40, 'C00000', Jc::CENTER, 5000);

        $firstPageSection->addText($this->escapeText(trim((string) ($event->name ?: 'Impreza'))), [
            'bold' => true,
            'size' => 24,
            'color' => '0070C0',
        ], ['alignment' => Jc::CENTER]);

        $run = $firstPageSection->addTextRun(['alignment' => Jc::CENTER]);
        $run->addText(
            ($event->start_date ? $event->start_date->format('d.m.Y') : '—')
            .' - '.($event->end_date ? $event->end_date->format('d.m.Y') : '—'),
            ['bold' => true, 'size' => 20, 'color' => '000000']
        );

        $firstPageSection->addTextBreak(1);

        $clientName = trim((string) ($event->client_name ?? ''));
        $table = $firstPageSection->addTable([
            'unit' => 'dxa',
            'alignment' => JcTable::CENTER,
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ]);

        $table->addRow();
        $table->addCell(3500)->addText('Data przygotowania oferty:', ['bold' => true]);
        $table->addCell(6400)->addText($generatedAt->format('d.m.Y'));

        $table->addRow();
        $table->addCell(3500)->addText('Termin ważności oferty:', ['bold' => true]);
        $table->addCell(6400)->addText('10 dni od daty przygotowania oferty');

        $table->addRow();
        $table->addCell(3500)->addText('Zamawiający:', ['bold' => true]);
        $table->addCell(6400)->addText($this->escapeText($clientName !== '' ? $clientName : '—'));

        $table->addRow();
        $table->addCell(3500)->addText('Organizator:', ['bold' => true]);
        $table->addCell(6400)->addText('Biuro Podróży RAFA, ul. Marii Konopnickiej 6, 00-491 Warszawa');

        $firstPageSection->addTextBreak(1);

        // --- SEKCJA 2: Treść oferty ---
        $normalSection = $phpWord->addSection($sectionOptions);
        $this->configureWordSectionBranding($normalSection);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'PROGRAM', 18, 'C00000');
        $normalSection->addTextBreak(1);

        $programPoints = $event->programPoints
            ->where('include_in_program', true)
            ->where('active', true)
            ->sortBy([['day', 'asc'], ['order', 'asc'], ['id', 'asc']])
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

        $priceParts = $this->resolvePriceIncludeExcludeFromTemplate($event);
        $listStyle = ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED];

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ($priceParts['includes'] as $item) {
            $normalSection->addListItem($item, 0, null, $listStyle);
        }

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA NIE ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ($priceParts['excludes'] as $item) {
            $normalSection->addListItem($item, 0, null, $listStyle);
        }

        $this->addDivider($normalSection);
        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'WARUNKI OFERTY', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ([
            'Program ma charakter ramowy, a kolejność realizacji punktów może ulec zmianie z przyczyn organizacyjnych.',
            'Kalkulacja ceny opiera się o aktualne stawki świadczeń i może wymagać aktualizacji przy istotnej zmianie kosztów.',
            'Cena za osobę zależy od ostatecznej liczby uczestników i obowiązuje dla wskazanych progów ilościowych.',
            'Ostateczne potwierdzenie świadczeń następuje po akceptacji oferty i podpisaniu umowy.',
            'Oferta jest ważna przez 7 dni od daty wygenerowania dokumentu, chyba że ustalono inaczej.',
        ] as $item) {
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

    /**
     * PhpWord czasem wstawia VML (&lt;w:pict&gt;) — LibreOffice/doxswap lepiej czyta DrawingML.
     */
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
        $displayW = 90;
        $displayH = (int) round($imgH * ($displayW / max(1, $imgW)));
        $widthEmu = $displayW * 12700;
        $heightEmu = $displayH * 12700;

        $drawingMl = '<w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$widthEmu.'" cy="'.$heightEmu.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:docPr id="1" name="Logo" descr="Biuro Podróży RAFA"/>'
            .'<wp:cNvGraphicFramePr>'
            .'<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            .'</wp:cNvGraphicFramePr>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr>'
            .'<pic:cNvPr id="0" name="logo.png"/>'
            .'<pic:cNvPicPr/>'
            .'</pic:nvPicPr>'
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
            $zip->addFromString('word/header1.xml', $newHeaderXml);
        }

        $zip->close();
    }

    protected function stripHtmlToSentence(string $text): string
    {
        // &nbsp; / encje HTML w OOXML są nieważne — LibreOffice wtedy otwiera tylko 1 stronę.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = strip_tags($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $this->escapeText($text);
    }

    /**
     * Treść „Cena zawiera / nie zawiera” ze szablonu WWW (event_price_descriptions).
     * Nagłówki sekcji Word zostają stałe (CENA ZAWIERA / CENA NIE ZAWIERA) — z HTML bierzemy tylko punkty.
     *
     * @return array{includes: list<string>, excludes: list<string>}
     */
    protected function resolvePriceIncludeExcludeFromTemplate(Event $event): array
    {
        $defaults = [
            'includes' => [
                'realizację programu wycieczki',
                'wyżywienie zgodnie z programem',
                'przejazd autokarem',
                'opłaty drogowe i parkingowe',
                'przewodników lokalnych',
                'opiekę pilota i organizację logistyczną',
                'ubezpieczenie zgodnie z charakterem imprezy',
                'podatek VAT',
            ],
            'excludes' => [
                'wydatków własnych uczestników',
                'punktów programu opisanych jako fakultatywne',
                'świadczeń nieujętych wyraźnie w sekcji „Co zawiera cena”',
            ],
        ];

        $html = trim((string) optional($event->eventTemplate?->eventPriceDescription?->first())->description);
        if ($html === '') {
            return $defaults;
        }

        $parsed = $this->parsePriceDescriptionHtml($html);
        if ($parsed['includes'] === [] && $parsed['excludes'] === []) {
            return $defaults;
        }

        return [
            'includes' => $parsed['includes'] !== [] ? $parsed['includes'] : $defaults['includes'],
            'excludes' => $parsed['excludes'] !== [] ? $parsed['excludes'] : $defaults['excludes'],
        ];
    }

    /**
     * @return array{includes: list<string>, excludes: list<string>}
     */
    protected function parsePriceDescriptionHtml(string $html): array
    {
        $working = $html;
        $working = preg_replace(
            '/<(strong|b)[^>]*>\s*(.*?)\s*<\/\1>/is',
            "\n@@HEAD@@$2@@/HEAD@@\n",
            $working
        ) ?? $working;
        $working = preg_replace('/<li[^>]*>/i', "\n- ", $working) ?? $working;
        $working = preg_replace('/<\/li>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<p[^>]*>/i', "\n", $working) ?? $working;
        $working = str_ireplace(['</p>'], "\n", $working);
        $working = preg_replace('/<br\s*\/?/i', "\n", $working) ?? $working;
        $working = strip_tags($working);
        $working = html_entity_decode($working, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $working = str_replace("\u{00A0}", ' ', $working);

        $includes = [];
        $excludes = [];
        $bucket = null; // 'includes' | 'excludes' | null

        foreach (preg_split('/\r\n|\r|\n/', $working) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^@@HEAD@@(.*?)@@\/HEAD@@$/s', $trimmed, $m)) {
                $heading = $this->stripHtmlToSentence($m[1]);
                if (preg_match('/cena\s+nie\s+zawiera/iu', $heading)) {
                    $bucket = 'excludes';
                } elseif (preg_match('/cena\s+zawiera/iu', $heading)) {
                    $bucket = 'includes';
                }

                continue;
            }

            // Nagłówek bez <strong>, np. zwykły tekst „Cena zawiera:”
            if (preg_match('/^cena\s+nie\s+zawiera\s*:?\s*$/iu', $trimmed)) {
                $bucket = 'excludes';

                continue;
            }
            if (preg_match('/^cena\s+zawiera\s*:?\s*$/iu', $trimmed)) {
                $bucket = 'includes';

                continue;
            }

            if ($bucket === null) {
                continue;
            }

            $content = str_starts_with($trimmed, '-')
                ? $this->stripHtmlToSentence(ltrim(substr($trimmed, 1)))
                : $this->stripHtmlToSentence($trimmed);

            if ($content === '') {
                continue;
            }

            if ($bucket === 'includes') {
                $includes[] = $content;
            } else {
                $excludes[] = $content;
            }
        }

        return [
            'includes' => $includes,
            'excludes' => $excludes,
        ];
    }

    protected function escapeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * @return list<array{label: string, price: int}>
     */
    protected function buildEventPriceRanges(Event $event): array
    {
        $offerTiers = [
            ['qty' => 20, 'label' => '20-24 + 2 opiekunów gratis'],
            ['qty' => 25, 'label' => '25-29 + 2 opiekunów gratis'],
            ['qty' => 30, 'label' => '30-34 + 3 opiekunów gratis'],
            ['qty' => 35, 'label' => '35-39 + 3 opiekunów gratis'],
            ['qty' => 40, 'label' => '40-55 + 4 opiekunów gratis'],
        ];

        $pricesByQty = $this->collectEventPricesByQty($event);

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

            $currency = strtoupper((string) ($row->currency?->symbol ?? $row->currency?->code ?? 'PLN'));
            if ($currency !== 'PLN') {
                continue;
            }

            if ($event->start_place_id
                && (int) ($row->start_place_id ?? 0) !== (int) $event->start_place_id
            ) {
                continue;
            }

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
        if ($qty <= 0) {
            return 0.0;
        }

        // Preferowane: kanoniczna cena imprezy (manual / EventCostCalculator).
        try {
            $resolved = (float) $event->resolvedPricePerPerson($qty);
            if ($resolved > 0) {
                return $resolved;
            }
        } catch (\Throwable) {
            // fallback poniżej
        }

        if (! $event->eventTemplate || ! $event->start_place_id) {
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
            // brak cennika dla tego progu
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
