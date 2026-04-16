<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventHotelPdfViewTest extends TestCase
{
    public function test_hotel_pdf_view_renders_hotel_notes(): void
    {
        $event = new Event([
            'id' => 77,
            'name' => 'Impreza hotelowa',
            'client_name' => 'Klient PDF',
            'start_date' => Carbon::parse('2026-04-15'),
            'end_date' => Carbon::parse('2026-04-16'),
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $html = view('pdf.event-document', [
            'audience' => 'hotel',
            'audienceLabel' => 'Pakiet dla hotelu',
            'event' => $event,
            'company' => [
                'name' => 'BP Rafa',
                'phone' => '123456789',
                'email' => 'test@example.com',
            ],
            'logoDataUri' => null,
            'generatedAt' => Carbon::parse('2026-04-03 12:00:00'),
            'participantCount' => 30,
            'staffCount' => 2,
            'driverCount' => 1,
            'gratisCount' => 3,
            'hotelNotes' => 'Późny check-in dla części grupy.',
            'programByDay' => collect(),
            'hotelPlan' => collect([
                [
                    'day' => 1,
                    'qty' => collect(),
                    'gratis' => collect(),
                    'staff' => collect(),
                    'driver' => collect(),
                    'notes' => null,
                ],
            ]),
            'agreements' => collect(),
            'individualAgreementRows' => collect(),
            'agreementsSummary' => [
                'total' => 0,
                'target_participants' => 30,
                'paid' => 0,
                'unpaid' => 30,
                'amount_due' => 0,
                'amount_paid' => 0,
                'amount_remaining' => 0,
                'payment_progress_label' => '0/30',
            ],
            'documentFocus' => [],
            'selectedSettlementDocuments' => collect(),
            'attachedFiles' => collect(),
        ])->render();

        $this->assertStringContainsString('Uwagi dla hotelu:', $html);
        $this->assertStringContainsString('Późny check-in dla części grupy.', $html);
    }
}