<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrictLocalPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Minimalna waluta PLN
        Currency::factory()->create(['name' => 'Polski złoty', 'code' => 'PLN']);
    }

    private function seedTemplateWithData(Place $warszawa, int $duration, bool $availability, bool $localPrice, string $name): EventTemplate
    {
        $template = EventTemplate::factory()->create([
            'duration_days' => $duration,
            'is_active' => true,
            'name' => $name,
            'start_place_id' => $warszawa->id,
        ]);

        if ($availability) {
            EventTemplateStartingPlaceAvailability::create([
                'event_template_id' => $template->id,
                'start_place_id' => $warszawa->id,
                'end_place_id' => $template->start_place_id,
                'available' => true,
                'note' => null,
            ]);
        }

        // current schema for event_template_qties only stores qty and timestamps
        $qty = EventTemplateQty::create([
            'qty' => 20,
        ]);

        if ($localPrice) {
            EventTemplatePricePerPerson::factory()->create([
                'event_template_id' => $template->id,
                'event_template_qty_id' => $qty->id,
                'currency_id' => Currency::first()->id,
                'start_place_id' => $warszawa->id,
                'price_per_person' => 123,
            ]);
        }

        return $template;
    }

    public function test_only_template_with_availability_and_local_price_is_listed()
    {
        $warszawa = Place::factory()->create(['name' => 'Warszawa', 'starting_place' => true]);
        $t1 = $this->seedTemplateWithData($warszawa, 4, true, true, 'Wycieczka OK');           // ma availability + price -> powinien być
        $t2 = $this->seedTemplateWithData($warszawa, 4, true, false, 'Bez ceny lokalnej');     // availability, brak ceny -> nie
        $t3 = $this->seedTemplateWithData($warszawa, 4, false, true, 'Sierota cenowa');        // cena ale brak availability -> nie

        $resp = $this->get('/warszawa/oferty?length_id=4');
        $resp->assertStatus(200);

        $html = $resp->getContent();

        $this->assertStringContainsString('Wycieczka OK', $html, 'Brak poprawnego szablonu z lokalną ceną');
        $this->assertStringNotContainsString('Bez ceny lokalnej', $html, 'Pojawił się szablon bez lokalnej ceny');
        $this->assertStringNotContainsString('Sierota cenowa', $html, 'Pojawił się szablon z ceną-sierotą bez availability');
    }

    public function test_template_is_hidden_when_availability_exists_only_for_wrong_end_place()
    {
        $zielonaGora = Place::factory()->create(['name' => 'Zielona Góra', 'starting_place' => true]);
        $warszawa = Place::factory()->create(['name' => 'Warszawa', 'starting_place' => true]);
        $krakow = Place::factory()->create(['name' => 'Kraków', 'starting_place' => true]);

        $template = EventTemplate::factory()->create([
            'duration_days' => 1,
            'is_active' => true,
            'name' => 'Pomiechówek test',
            'slug' => 'pomiechowek-test',
            'start_place_id' => $warszawa->id,
        ]);

        EventTemplateStartingPlaceAvailability::create([
            'event_template_id' => $template->id,
            'start_place_id' => $zielonaGora->id,
            // Celowo zły end_place: inny niż start_place_id szablonu.
            'end_place_id' => $krakow->id,
            'available' => true,
        ]);

        $qty = EventTemplateQty::create(['qty' => 40]);
        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => Currency::first()->id,
            'start_place_id' => $zielonaGora->id,
            'price_per_person' => 399,
        ]);

        $response = $this->get('/zielona-gora/oferty?length_id=1');
        $response->assertStatus(200);
        $response->assertDontSee('Pomiechówek test');
    }
}
