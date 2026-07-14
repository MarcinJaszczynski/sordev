<?php
$content = file_get_contents('app/Filament/Forms/ReservationFormFields.php');
$new = <<<'PHP'
    public static function hotelSelectionNotesPlaceholder(?Event $event = null): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('hotel_selection_notes')
            ->label('Wybrane hotele w planie imprezy i ich uwagi')
            ->content(function (Get $get) use ($event): HtmlString|string {
                $resolved = $event ?? (($id = $get('event_id')) ? Event::with(['hotelStays.contractor'])->find($id) : null);

                if (!$resolved) {
                    return 'Brak wybranej imprezy.';
                }

                $stays = $resolved->hotelStays()->with('contractor')->get();
                if ($stays->isEmpty()) {
                    return 'Brak zaplanowanych noclegów w zakładce Hotele.';
                }

                $html = '<div class="space-y-4">';
                foreach ($stays as $stay) {
                    $hotelName = $stay->contractor ? $stay->contractor->name : 'Nie wybrano hotelu';
                    $html .= '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm">';
                    $html .= '<div class="font-semibold text-gray-900 dark:text-gray-100">Noc ' . $stay->day . ' — ' . e($hotelName) . '</div>';
                    
                    if (filled($stay->offer_notes)) {
                        $html .= '<div class="mt-2"><strong class="text-xs text-gray-500 uppercase tracking-wide">Oferta / w cenie:</strong><br/>' . nl2br(e($stay->offer_notes)) . '</div>';
                    }
                    if (filled($stay->notes)) {
                        $html .= '<div class="mt-2"><strong class="text-xs text-gray-500 uppercase tracking-wide">Uwagi operacyjne:</strong><br/>' . nl2br(e($stay->notes)) . '</div>';
                    }
                    if (blank($stay->offer_notes) && blank($stay->notes)) {
                        $html .= '<div class="mt-1 text-xs text-gray-500">Brak uwag.</div>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';

                return new HtmlString($html);
            })
            ->columnSpanFull();
    }
PHP;

$content = preg_replace('/public static function hotelSelectionNotesPlaceholder.*?\n    }/s', $new, $content);
file_put_contents('app/Filament/Forms/ReservationFormFields.php', $content);
echo "Replaced.\n";
