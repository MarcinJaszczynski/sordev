<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventParticipantListTemplateBuilder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventParticipantListTemplateController extends Controller
{
    public function __invoke(Event $event, string $format = 'csv'): StreamedResponse|BinaryFileResponse
    {
        $format = strtolower($format);

        if ($format === 'csv') {
            return app(EventParticipantListTemplateBuilder::class)->downloadCsv($event);
        }

        $builder = app(EventParticipantListTemplateBuilder::class);
        $rows = collect($builder->instructionLines())
            ->merge([$builder->headings()])
            ->merge($builder->exampleRows());

        return Excel::download(
            new class($rows) implements \Maatwebsite\Excel\Concerns\FromCollection
            {
                public function __construct(private readonly \Illuminate\Support\Collection $rows) {}

                public function collection()
                {
                    return $this->rows;
                }
            },
            Str::slug($event->name).'-lista-uczestnikow-szablon.xlsx',
        );
    }
}
