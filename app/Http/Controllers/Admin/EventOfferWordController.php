<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocument;
use App\Services\Documents\WordOfferContent;
use App\Services\EventOrderingPartyService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use ZipArchive;

class EventOfferWordController extends Controller
{
    public function __construct(
        private readonly WordOfferContent $content,
        private readonly EventOrderingPartyService $orderingParties,
    ) {}

    public function __invoke(Event $event)
    {
        Gate::authorize('view', $event);

        $event->load([
            'startPlace',
            'eventTemplate.eventPriceDescription',
            'eventTemplate.eventTypes',
            'qtyVariants',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'pricePerPerson.eventTemplateQty',
            'pricePerPerson.currency',
            'orderingContractors',
            'hotelStays.contractor',
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
        $metaLine = $this->metaParagraphStyle();

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

        // ~2 cm (1 cm ≈ 567 twipów).
        $this->addCoverTitleSpacer($firstPageSection, 1134);

        $this->addHeading($firstPageSection, 'Oferta wycieczki', 40, 'C00000', Jc::CENTER, 0);

        $tripName = trim((string) ($event->name ?: 'Impreza'));
        $firstPageSection->addText($this->escapeText($tripName), [
            'bold' => true,
            'size' => 22,
            'color' => '0070C0',
        ], [
            'alignment' => Jc::CENTER,
            'spaceBefore' => 200,
            'spaceAfter' => 120,
        ]);

        $startDate = $event->start_date ? $event->start_date->format('d.m.Y') : '—';
        $endDate = $event->end_date ? $event->end_date->format('d.m.Y') : '—';
        $dateRange = $startDate.' – '.$endDate;
        $firstPageSection->addText(
            $dateRange,
            ['bold' => true, 'size' => 20, 'color' => '000000'],
            [
                'alignment' => Jc::CENTER,
                'spaceBefore' => 0,
                'spaceAfter' => 0,
            ]
        );

        $firstPageSection->addTextBreak(4);

        $table = $firstPageSection->addTable([
            'unit' => 'dxa',
            'alignment' => JcTable::START,
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ]);

        $labelStyle = ['bold' => true, 'size' => 11, 'color' => '000000'];
        $valueStyle = ['size' => 11, 'color' => '000000'];

        $this->addMetaRow($table, 'Data przygotowania oferty:', $generatedAt->format('d.m.Y'), $labelStyle, $valueStyle, $metaLine);
        $this->addMetaRow($table, 'Termin ważności oferty:', $this->content->offerValidityLabel(), $labelStyle, $valueStyle, $metaLine);

        // Pusty wiersz ≈ jeden odstęp sekcji.
        $table->addRow();
        $table->addCell(4200)->addText('', $valueStyle, $metaLine);
        $table->addCell(5700)->addText('', $valueStyle, $metaLine);

        $table->addRow();
        $table->addCell(4200)->addText('Zamawiający:', $labelStyle, $metaLine);
        $clientCell = $table->addCell(5700);
        $this->fillOrderingPartyCell($clientCell, $event, $valueStyle, $metaLine);

        $table->addRow();
        $table->addCell(4200)->addText('', $valueStyle, $metaLine);
        $table->addCell(5700)->addText('', $valueStyle, $metaLine);

        $table->addRow();
        $table->addCell(4200)->addText('Organizator:', $labelStyle, $metaLine);
        $orgCell = $table->addCell(5700);
        foreach (WordOfferContent::ORGANIZER_LINES as $line) {
            $orgCell->addText($this->escapeText($line), $valueStyle, $metaLine);
        }

        // --- SEKCJA 2: Treść oferty ---
        $normalSection = $phpWord->addSection($sectionOptions);
        $this->configureWordSectionBranding($normalSection);

        $normalSection->addText($this->escapeText($tripName), [
            'bold' => true,
            'size' => 22,
            'color' => '0070C0',
        ], [
            'alignment' => Jc::CENTER,
            'spaceBefore' => 200,
            'spaceAfter' => 80,
        ]);
        $normalSection->addText(
            $dateRange,
            ['bold' => true, 'size' => 16, 'color' => '000000'],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 200]
        );

        $this->addHeading($normalSection, 'Program wycieczki', 18, 'C00000');
        $normalSection->addTextBreak(1);

        $justified = $this->justifiedParagraphStyle();
        $listStyle = ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED];

        $programPoints = $event->programPoints
            ->where('include_in_program', true)
            ->where('active', true)
            ->sortBy([['day', 'asc'], ['order', 'asc'], ['id', 'asc']])
            ->values();

        if ($programPoints->isEmpty()) {
            $normalSection->addText(
                'Program szczegółowy przygotujemy pod wymagania grupy.',
                null,
                $justified
            );
        } else {
            $byDay = $programPoints->groupBy(fn ($point) => (int) ($point->day ?? 1))->sortKeys();
            foreach ($byDay as $day => $points) {
                $dayLabel = $event->isFacultativeProgramDay((int) $day)
                    ? 'Fakultatywnie proponujemy:'
                    : 'Dzień '.$day;
                $normalSection->addText($dayLabel, ['bold' => true, 'color' => '0070C0']);

                foreach ($points as $point) {
                    $title = trim((string) $point->name);
                    $descAllowed = (bool) ($point->show_description ?? true);
                    $desc = $descAllowed ? trim((string) ($point->description ?? '')) : '';
                    $boldTitle = (bool) ($point->show_title_style ?? true);

                    $listRun = $normalSection->addListItemRun(0, $listStyle, $justified);
                    $listRun->addText(
                        $this->escapeText($title),
                        $boldTitle ? ['bold' => true] : null
                    );
                    if ($desc !== '') {
                        $listRun->addText(' – '.$this->stripHtmlToSentence($desc));
                    }
                }
                $normalSection->addTextBreak(1);
            }
        }

        $this->addDivider($normalSection);
        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENNIK', 18, 'C00000');
        $normalSection->addTextBreak(1);

        $priceRanges = $this->buildEventPriceRanges($event);
        if ($priceRanges === []) {
            $normalSection->addText('Cennik zostanie przedstawiony po doprecyzowaniu liczby uczestników.');
        } else {
            foreach ($priceRanges as $row) {
                $run = $normalSection->addTextRun();
                $run->addText(
                    $this->escapeText($row['amount']),
                    ['bold' => true, 'size' => 12]
                );
                $run->addText(
                    $this->escapeText($row['detail']),
                    ['bold' => false, 'size' => 12]
                );
            }
        }

        $this->addDivider($normalSection);

        $priceParts = $this->resolvePriceIncludeExcludeFromTemplate($event);

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ($priceParts['includes'] as $item) {
            $normalSection->addListItem($item, 0, null, $listStyle, $justified);
        }

        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'CENA NIE ZAWIERA', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ($priceParts['excludes'] as $item) {
            $normalSection->addListItem($item, 0, null, $listStyle, $justified);
        }

        $coreDays = $event->resolveCoreProgramDaysCount();
        if ($this->content->shouldShowAccommodation($coreDays)) {
            $hotelNames = $event->hotelStays
                ->map(fn ($stay) => trim((string) ($stay->contractor?->name ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->addDivider($normalSection);
            $normalSection->addTextBreak(1);
            $this->addHeading($normalSection, 'ZAKWATEROWANIE', 18, 'C00000');
            $normalSection->addTextBreak(1);
            foreach ($this->content->accommodationLines($hotelNames) as $line) {
                $normalSection->addListItem($this->escapeText($line), 0, null, $listStyle, $justified);
            }
        }

        $isForeign = (bool) ($event->eventTemplate?->isForeignTrip() ?? false);

        $this->addDivider($normalSection);
        $normalSection->addTextBreak(1);
        $this->addHeading($normalSection, 'UWAGI', 18, 'C00000');
        $normalSection->addTextBreak(1);
        foreach ($this->content->notes($isForeign) as $item) {
            $normalSection->addListItem($this->escapeText($item), 0, null, $listStyle, $justified);
        }

        $normalSection->addTextBreak(1);
        $normalSection->addText('Dokument wygenerowany: '.$generatedAt->format('d.m.Y H:i'), ['size' => 9, 'color' => '777777']);

        $fileName = 'oferta-wycieczki-'.$event->id.'-'.$generatedAt->format('Ymd-His').'.docx';
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
            'name' => 'Oferta wycieczki - '.($event->name ?: ('Event #'.$event->id)),
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

    /**
     * @param  \PhpOffice\PhpWord\Element\Table  $table
     * @param  array<string, mixed>  $labelStyle
     * @param  array<string, mixed>  $valueStyle
     * @param  array<string, mixed>  $para
     */
    protected function addMetaRow($table, string $label, string $value, array $labelStyle, array $valueStyle, array $para): void
    {
        $table->addRow();
        $table->addCell(4200)->addText($label, $labelStyle, $para);
        $table->addCell(5700)->addText($this->escapeText($value), $valueStyle, $para);
    }

    /**
     * @param  array<string, mixed>  $valueStyle
     * @param  array<string, mixed>  $para
     */
    protected function fillOrderingPartyCell(Cell $cell, Event $event, array $valueStyle, array $para): void
    {
        $parties = $this->orderingParties->partiesForWordDocument($event);

        if ($parties === []) {
            $cell->addText('—', $valueStyle, $para);

            return;
        }

        foreach ($parties as $index => $party) {
            if ($index > 0) {
                $cell->addText('', $valueStyle, $para);
            }

            $institution = trim((string) ($party['institution'] ?? ''));
            $person = trim((string) ($party['person'] ?? ''));

            if ($institution !== '') {
                $cell->addText($this->escapeText($institution), $valueStyle, $para);
            }
            if ($person !== '') {
                $cell->addText($this->escapeText($person), $valueStyle, $para);
            }
            if ($institution === '' && $person === '') {
                $cell->addText('—', $valueStyle, $para);
            }
        }
    }

    /**
     * Interlinia 1,5 (240 = single → 360 = 1.5).
     *
     * @return array<string, mixed>
     */
    protected function metaParagraphStyle(): array
    {
        return [
            'spaceBefore' => 40,
            'spaceAfter' => 40,
            'lineHeight' => 1.5,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function justifiedParagraphStyle(): array
    {
        return [
            'alignment' => Jc::BOTH,
        ];
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
            'spaceAfter' => 0,
        ]);
    }

    /**
     * Pionowy odstęp na okładce odporny na różnice Word / Pages / LibreOffice.
     */
    protected function addCoverTitleSpacer($section, int $heightTwips): void
    {
        $contentWidth = 9906;
        $table = $section->addTable([
            'unit' => 'dxa',
            'width' => $contentWidth,
            'alignment' => JcTable::CENTER,
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ]);
        $table->addRow($heightTwips, ['exactHeight' => true]);
        $cell = $table->addCell($contentWidth, [
            'valign' => 'top',
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ]);
        $cell->addText('', ['size' => 1], [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
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
                'wrappingStyle' => 'inline',
            ]);
        }

        $footer = $section->addFooter();
        $contentWidth = 9906;
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

        if (! preg_match('/r:id=["\'](rId\d+)["\']/', $headerXml, $m)) {
            $zip->close();

            return;
        }

        $rId = $m[1];
        [$imgW, $imgH] = @getimagesize($logoPath) ?: [300, 76];
        $displayW = 90;
        $displayH = (int) round($imgH * ($displayW / max(1, $imgW)));
        $widthEmu = (int) round($displayW * 9525);
        $heightEmu = (int) round($displayH * 9525);

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
            $zip->addFromString('word/header1.xml', $newHeaderXml);
        }

        $zip->close();
    }

    protected function stripHtmlToSentence(string $text): string
    {
        return $this->escapeText($this->content->plainText($text));
    }

    /**
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

        $parsed = $this->content->parsePriceDescriptionHtml($html);
        if ($parsed['includes'] === [] && $parsed['excludes'] === []) {
            return $defaults;
        }

        return [
            'includes' => $parsed['includes'] !== [] ? array_map(
                fn (string $item): string => $this->escapeText($item),
                $parsed['includes']
            ) : $defaults['includes'],
            'excludes' => $parsed['excludes'] !== [] ? array_map(
                fn (string $item): string => $this->escapeText($item),
                $parsed['excludes']
            ) : $defaults['excludes'],
        ];
    }

    protected function escapeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * @return list<array{from: int, to: int, price: int, amount: string, detail: string}>
     */
    protected function buildEventPriceRanges(Event $event): array
    {
        $tiers = $this->content->eventOfferTiers();
        $pricesByQty = [];
        $extrasByQty = [];
        $eventPrices = $this->collectEventPricesByQty($event);

        $uniqueQtys = array_values(array_unique(array_map(fn (array $t): int => $t['qty'], $tiers)));

        foreach ($uniqueQtys as $qty) {
            $templatePrice = $this->lookupTemplatePriceForQty($event, $qty);
            if ($templatePrice > 0) {
                $pricesByQty[$qty] = $templatePrice;
                $extrasByQty[$qty] = $this->lookupTemplateExtraCurrencyLabels($event, $qty);

                continue;
            }

            $templateCalculated = $this->calculateTemplatePriceForQty($event, $qty);
            if ($templateCalculated > 0) {
                $pricesByQty[$qty] = $templateCalculated;
                $extrasByQty[$qty] = $this->lookupTemplateExtraCurrencyLabels($event, $qty);

                continue;
            }

            if (($eventPrices[$qty] ?? 0) > 0) {
                $pricesByQty[$qty] = $eventPrices[$qty];
                $extrasByQty[$qty] = $this->lookupEventExtraCurrencyLabels($event, $qty);

                continue;
            }

            $eventCalculated = $this->calculateEventPriceForQty($event, $qty);
            if ($eventCalculated > 0) {
                $pricesByQty[$qty] = $eventCalculated;
                $extrasByQty[$qty] = $this->lookupEventExtraCurrencyLabels($event, $qty);
            }
        }

        $ranges = [];
        foreach ($tiers as $tier) {
            $price = (float) ($pricesByQty[$tier['qty']] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $rounded = (int) ceil($price / 5) * 5;
            $extras = $extrasByQty[$tier['qty']] ?? [];

            $parts = $this->content->formatPriceLineParts(
                $rounded,
                $tier['from'],
                $tier['to'],
                $tier['gratis'],
                $extras
            );

            $ranges[] = [
                'from' => $tier['from'],
                'to' => $tier['to'],
                'price' => $rounded,
                'amount' => $parts['amount'],
                'detail' => $parts['detail'],
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
        $row = $this->latestTemplatePriceRow($event, $qty, true);

        return $row ? (float) $row->price_per_person : 0.0;
    }

    /**
     * @return list<string>
     */
    protected function lookupTemplateExtraCurrencyLabels(Event $event, int $qty): array
    {
        if (! $event->event_template_id || $qty <= 0) {
            return [];
        }

        $qtyId = \App\Models\EventTemplateQty::query()
            ->where('qty', $qty)
            ->value('id');

        if (! $qtyId) {
            return [];
        }

        $query = \App\Models\EventTemplatePricePerPerson::query()
            ->with('currency')
            ->where('event_template_id', $event->event_template_id)
            ->where('event_template_qty_id', $qtyId)
            ->where('price_per_person', '>', 0);

        if ($event->start_place_id) {
            $query->where('start_place_id', $event->start_place_id);
        }

        $grouped = [];
        foreach ($query->orderByDesc('id')->get() as $row) {
            $currency = strtoupper(trim((string) ($row->currency?->symbol ?? $row->currency?->code ?? '')));
            if ($currency === '' || $currency === 'PLN' || str_contains(strtoupper((string) ($row->currency?->name ?? '')), 'ZŁOT')) {
                continue;
            }

            $amount = (int) ceil((float) $row->price_per_person);
            if ($amount <= 0) {
                continue;
            }

            if (! isset($grouped[$currency]) || $amount < $grouped[$currency]) {
                $grouped[$currency] = $amount;
            }
        }

        uksort($grouped, function (string $a, string $b): int {
            if ($a === $b) {
                return 0;
            }
            if ($a === 'EUR') {
                return -1;
            }
            if ($b === 'EUR') {
                return 1;
            }

            return strcmp($a, $b);
        });

        $labels = [];
        foreach ($grouped as $currency => $amount) {
            $labels[] = '+ '.$amount.' '.$currency;
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    protected function lookupEventExtraCurrencyLabels(Event $event, int $qty): array
    {
        $grouped = [];

        foreach ($event->pricePerPerson as $row) {
            $rowQty = (int) ($row->eventTemplateQty->qty ?? 0);
            if ($rowQty !== $qty) {
                continue;
            }

            $price = (float) ($row->price_per_person ?? 0);
            if ($price <= 0) {
                continue;
            }

            $currency = strtoupper(trim((string) ($row->currency?->symbol ?? $row->currency?->code ?? '')));
            if ($currency === '' || $currency === 'PLN') {
                continue;
            }

            if ($event->start_place_id
                && (int) ($row->start_place_id ?? 0) !== (int) $event->start_place_id
            ) {
                continue;
            }

            $amount = (int) ceil($price);
            if (! isset($grouped[$currency]) || $amount < $grouped[$currency]) {
                $grouped[$currency] = $amount;
            }
        }

        if (isset($grouped['EUR'])) {
            $eur = $grouped['EUR'];
            unset($grouped['EUR']);
            $grouped = ['EUR' => $eur] + $grouped;
        }

        $labels = [];
        foreach ($grouped as $currency => $amount) {
            $labels[] = '+ '.$amount.' '.$currency;
        }

        return $labels;
    }

    protected function latestTemplatePriceRow(Event $event, int $qty, bool $plnOnly)
    {
        if (! $event->event_template_id || $qty <= 0) {
            return null;
        }

        $qtyId = \App\Models\EventTemplateQty::query()
            ->where('qty', $qty)
            ->value('id');

        if (! $qtyId) {
            return null;
        }

        $query = \App\Models\EventTemplatePricePerPerson::query()
            ->with('currency')
            ->where('event_template_id', $event->event_template_id)
            ->where('event_template_qty_id', $qtyId)
            ->where('price_per_person', '>', 0);

        if ($event->start_place_id) {
            $query->where('start_place_id', $event->start_place_id);
        }

        foreach ($query->orderByDesc('id')->get() as $row) {
            $currency = strtoupper((string) ($row->currency?->symbol ?? $row->currency?->code ?? 'PLN'));
            $isPln = $currency === 'PLN' || str_contains(strtoupper((string) ($row->currency?->name ?? '')), 'ZŁOT');
            if ($plnOnly && ! $isPln) {
                continue;
            }
            if (! $plnOnly && $isPln) {
                continue;
            }

            return $row;
        }

        return null;
    }

    protected function calculateTemplatePriceForQty(Event $event, int $qty): float
    {
        if ($qty <= 0 || ! $event->eventTemplate || ! $event->start_place_id) {
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

            return $price > 0 ? $price : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected function calculateEventPriceForQty(Event $event, int $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        try {
            $resolved = (float) $event->resolvedPricePerPerson($qty);

            return $resolved > 0 ? $resolved : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
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
