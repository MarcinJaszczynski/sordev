<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageAvailabilityRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_pretty_package_redirects_to_offers_when_template_is_unavailable_for_region(): void
    {
        $zielonaGora = Place::factory()->create(['name' => 'Zielona Góra', 'starting_place' => true]);
        $warszawa = Place::factory()->create(['name' => 'Warszawa', 'starting_place' => true]);

        $template = EventTemplate::factory()->create([
            'name' => 'Pomiechowek Park Doliny Wkry',
            'slug' => 'pomiechowek-park-doliny-wkry',
            'duration_days' => 1,
            'is_active' => true,
            'start_place_id' => $warszawa->id,
        ]);

        $qty = EventTemplateQty::create(['qty' => 40]);
        $pln = Currency::factory()->create([
            'code' => 'PLN',
            'symbol' => 'PLN',
            'name' => 'Polski złoty',
        ]);

        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $pln->id,
            'start_place_id' => $zielonaGora->id,
            'price_per_person' => 350,
        ]);

        $response = $this->get(route('package.pretty', [
            'regionSlug' => 'zielona-gora',
            'dayLength' => '1-dniowe',
            'id' => $template->id,
            'slug' => $template->slug,
        ]));

        $response->assertRedirect(route('packages', ['regionSlug' => 'zielona-gora']));
    }

    public function test_word_export_redirects_to_offers_when_template_is_unavailable_for_region(): void
    {
        $this->actingAs(User::factory()->create());

        $zielonaGora = Place::factory()->create(['name' => 'Zielona Góra', 'starting_place' => true]);
        $warszawa = Place::factory()->create(['name' => 'Warszawa', 'starting_place' => true]);

        $template = EventTemplate::factory()->create([
            'name' => 'Oferta testowa',
            'slug' => 'oferta-testowa',
            'duration_days' => 1,
            'is_active' => true,
            'start_place_id' => $warszawa->id,
        ]);

        $qty = EventTemplateQty::create(['qty' => 40]);
        $pln = Currency::factory()->create([
            'code' => 'PLN',
            'symbol' => 'PLN',
            'name' => 'Polski złoty',
        ]);

        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $pln->id,
            'start_place_id' => $zielonaGora->id,
            'price_per_person' => 420,
        ]);

        $response = $this->post(route('package.pretty.word', [
            'regionSlug' => 'zielona-gora',
            'dayLength' => '1-dniowe',
            'id' => $template->id,
            'slug' => $template->slug,
        ]), [
            'organization_name' => 'Szkoła 1',
        ]);

        $response->assertRedirect(route('packages', ['regionSlug' => 'zielona-gora']));
    }
}
