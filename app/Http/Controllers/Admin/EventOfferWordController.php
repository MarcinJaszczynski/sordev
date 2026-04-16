<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocument;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;

class EventOfferWordController extends Controller
{
    public function __invoke(Event $event)
    {
        $event->load([
            'startPlace',
            'eventTemplate',
            'programPoints' => fn ($query) => $query->orderBy('day')->orderBy('order'),
            'pricePerPerson.eventTemplateQty',
            'pricePerPerson.currency',
        ]);

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(
            (new Language('pl-PL'))->setLangId(1045)
        );
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $generatedAt = now();
        $this->configureWordSectionBranding($section, $generatedAt);

        $this->addHeading($section, 'OFERTA IMPREZY', 22, 'C00000');
        $this->addHeading($section, (string) ($event->name ?: 'Impreza'), 16, '111111');

        $section->addTextBreak(1);
        $section->addText('Miejsce wyjazdu: ' . ($event->startPlace?->name ?: 'do ustalenia'));
        $section->addText('Termin: ' . ($event->start_date ? $event->start_date->format('d.m.Y') : '—') . ' - ' . ($event->end_date ? $event->end_date->format('d.m.Y') : '—'));
        $section->addText('Przewidywana liczba uczestników: ' . ((int) ($event->participant_count ?? 0) > 0 ? $event->participant_count : 'do ustalenia'));

        $section->addTextBreak(1);
        $this->addHeading($section, 'Opis', 14, '0070C0');
        $description = trim((string) ($event->eventTemplate?->event_description ?: $event->notes ?: 'Opis zostanie doprecyzowany podczas rozmowy.'));
        $this->addMultilineText($section, $description);

        $section->addTextBreak(1);
        $this->addHeading($section, 'Program', 14, '0070C0');
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
            $section->addText('Program szczegółowy przygotujemy pod wymagania grupy.');
        } else {
            $byDay = $programPoints->groupBy(fn ($point) => (int) ($point->day ?? 1))->sortKeys();
            foreach ($byDay as $day => $points) {
                $section->addText('Dzień ' . $day, ['bold' => true, 'color' => '0070C0']);
                foreach ($points as $point) {
                    $line = trim((string) $point->name);
                    $desc = trim((string) ($point->description ?? ''));
                    if ($desc !== '') {
                        $line .= ' - ' . $this->stripHtmlToSentence($desc);
                    }
                    $section->addListItem($line, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
                }
                $section->addTextBreak(1);
            }
        }

        $section->addTextBreak(1);
        $this->addHeading($section, 'Cennik', 14, 'C00000');
        $priceRanges = $this->buildEventPriceRanges($event);

        if (empty($priceRanges)) {
            $price = $event->resolvedPricePerPerson(max(1, (int) ($event->participant_count ?? 1)));
            if ($price > 0) {
                $section->addText(number_format($price, 0, ',', ' ') . ' PLN za osobę (cena orientacyjna dla aktualnej imprezy).', ['bold' => true]);
            } else {
                $section->addText('Cennik zostanie przedstawiony po doprecyzowaniu liczby uczestników.');
            }
        } else {
            foreach ($priceRanges as $row) {
                $section->addText(number_format((float) $row['price'], 0, ',', ' ') . ' PLN', ['bold' => true, 'size' => 13]);
                $section->addText('za osobę dla grupy ' . $row['label']);
                $section->addTextBreak(1);
            }
        }

        $section->addText('Zapytaj o ofertę dla innej ilości osób.', ['italic' => true, 'color' => '0070C0']);

        $section->addTextBreak(1);
        $this->addHeading($section, 'Co zawiera cena', 14, '0070C0');
        $defaultIncludes = [
            'realizację programu wycieczki',
            'przejazd autokarem',
            'opiekę pilota i organizację logistyczną',
            'ubezpieczenie zgodnie z charakterem imprezy',
            'podatek VAT',
        ];

        foreach ($defaultIncludes as $item) {
            $section->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $section->addTextBreak(1);
        $this->addHeading($section, 'Cena nie zawiera', 14, '0070C0');
        $defaultExcludes = [
            'wydatków własnych uczestników',
            'punktów programu opisanych jako fakultatywne',
            'świadczeń nieujętych wyraźnie w sekcji „Co zawiera cena”',
        ];
        foreach ($defaultExcludes as $item) {
            $section->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $section->addTextBreak(1);
        $this->addHeading($section, 'Warunki oferty', 14, '0070C0');
        $offerTerms = [
            'Program ma charakter ramowy, a kolejność realizacji punktów może ulec zmianie z przyczyn organizacyjnych.',
            'Kalkulacja ceny opiera się o aktualne stawki świadczeń i może wymagać aktualizacji przy istotnej zmianie kosztów.',
            'Cena za osobę zależy od ostatecznej liczby uczestników i obowiązuje dla wskazanych progów ilościowych.',
            'Ostateczne potwierdzenie świadczeń następuje po akceptacji oferty i podpisaniu umowy.',
            'Oferta jest ważna przez 7 dni od daty wygenerowania dokumentu, chyba że ustalono inaczej.',
        ];
        foreach ($offerTerms as $item) {
            $section->addListItem($item, 0, null, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
        }

        $section->addTextBreak(1);
        $section->addText('Dokument wygenerowany: ' . $generatedAt->format('d.m.Y H:i'), ['size' => 9, 'color' => '777777']);

        $fileName = 'oferta-imprezy-' . $event->id . '-' . $generatedAt->format('Ymd-His') . '.docx';
        $relativePath = 'event-offers/' . $fileName;
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (!is_dir(dirname($absolutePath))) {
            @mkdir(dirname($absolutePath), 0775, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $disk = Storage::disk('public');
        EventDocument::create([
            'event_id' => $event->id,
            'name' => 'Oferta imprezy - ' . ($event->name ?: ('Event #' . $event->id)),
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

    protected function addHeading($section, string $text, int $size, string $color): void
    {
        $section->addText($text, [
            'bold' => true,
            'size' => $size,
            'color' => $color,
        ], ['alignment' => 'center']);
    }

    protected function configureWordSectionBranding($section, $generatedAt): void
    {
        $header = $section->addHeader();
        $header->addText(
            'Biuro Podrozy RAFA',
            ['bold' => true, 'size' => 11, 'color' => '0070C0'],
            ['alignment' => 'center']
        );
        $header->addText(
            'Oferta imprezy',
            ['size' => 10, 'color' => '555555'],
            ['alignment' => 'center']
        );

        $footer = $section->addFooter();
        $footer->addText(
            'Biuro Podrozy RAFA | ul. Marii Konopnickiej 6 | 00-491 Warszawa',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'center']
        );
        $footer->addText(
            'tel. +48 606 102 243 | rafa@bprafa.pl | www.bprafa.pl | NIP 716-250-87-61',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'center']
        );
        $footer->addText(
            'Data wygenerowania: ' . $generatedAt->format('d.m.Y H:i'),
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'left']
        );
        $footer->addPreserveText(
            'Strona {PAGE} z {NUMPAGES}',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'right']
        );
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
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    protected function buildEventPriceRanges(Event $event): array
    {
        $rows = $event->pricePerPerson
            ->filter(function ($row) use ($event) {
                if ((float) ($row->price_per_person ?? 0) <= 0) {
                    return false;
                }

                $currency = strtoupper((string) ($row->currency->symbol ?? $row->currency->code ?? ''));
                if ($currency !== 'PLN') {
                    return false;
                }

                if ($event->start_place_id) {
                    return (int) ($row->start_place_id ?? 0) === (int) $event->start_place_id;
                }

                return true;
            })
            ->groupBy(fn ($row) => (int) ($row->eventTemplateQty->qty ?? 0))
            ->map(function ($group) {
                return $group->sortByDesc('id')->first();
            })
            ->filter(fn ($row, $qty) => (int) $qty > 0)
            ->sortByDesc(fn ($row) => (int) ($row->eventTemplateQty->qty ?? 0))
            ->values();

        $pricesByQty = $rows
            ->mapWithKeys(function ($row) {
                $qty = (int) ($row->eventTemplateQty->qty ?? 0);
                return $qty > 0 ? [$qty => (float) $row->price_per_person] : [];
            });

        // Keep explicit quantity steps used on the template page and merge with saved qty variants.
        $templateSteps = collect([20, 25, 30, 35, 40, 45, 50, 55]);
        $allQtySteps = $templateSteps
            ->merge($pricesByQty->keys())
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0)
            ->unique()
            ->sort()
            ->values();

        if ($allQtySteps->isEmpty()) {
            return [];
        }

        $ranges = [];

        foreach ($allQtySteps as $qty) {
            $basePrice = (float) ($pricesByQty->get($qty) ?? 0);
            $resolvedPrice = (float) $event->resolvedPricePerPerson($qty);
            $price = $basePrice > 0 ? $basePrice : $resolvedPrice;

            if ($price <= 0) {
                continue;
            }

            $ranges[] = [
                'label' => (string) $qty . ' osób',
                'price' => (int) ceil($price / 5) * 5,
            ];
        }

        return $ranges;
    }
}
