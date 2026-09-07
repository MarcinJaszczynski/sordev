<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Place;
use App\Support\Seo\LlmsTxtBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LlmsTxtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::factory()->create(['name' => 'Polski złoty', 'code' => 'PLN']);
        Cache::flush();
    }

    public function test_llms_txt_lists_strict_local_connection_with_destination_label(): void
    {
        $gdansk = Place::factory()->create(['name' => 'Gdańsk', 'starting_place' => true]);
        $torun = Place::factory()->create(['name' => 'Toruń', 'starting_place' => false]);
        $hub = Place::factory()->create(['name' => 'Hub Start', 'starting_place' => true]);

        $template = EventTemplate::factory()->create([
            'name' => 'Toruń Stare Miasto',
            'slug' => 'torun-stare-miasto',
            'duration_days' => 1,
            'is_active' => true,
            'start_place_id' => $hub->id,
            'end_place_id' => $torun->id,
        ]);

        EventTemplateStartingPlaceAvailability::create([
            'event_template_id' => $template->id,
            'start_place_id' => $gdansk->id,
            'end_place_id' => $hub->id,
            'available' => true,
        ]);

        $qty = EventTemplateQty::create(['qty' => 40]);
        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => Currency::first()->id,
            'start_place_id' => $gdansk->id,
            'price_per_person' => 199,
        ]);

        $twoDay = EventTemplate::factory()->create([
            'name' => 'Toruń weekend',
            'slug' => 'torun-weekend',
            'duration_days' => 2,
            'is_active' => true,
            'start_place_id' => $hub->id,
            'end_place_id' => $torun->id,
        ]);
        EventTemplateStartingPlaceAvailability::create([
            'event_template_id' => $twoDay->id,
            'start_place_id' => $gdansk->id,
            'end_place_id' => $hub->id,
            'available' => true,
        ]);
        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $twoDay->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => Currency::first()->id,
            'start_place_id' => $gdansk->id,
            'price_per_person' => 399,
        ]);

        // Oferta bez lokalnej ceny — nie powinna trafić do katalogu.
        $hidden = EventTemplate::factory()->create([
            'name' => 'Ukryta bez ceny',
            'slug' => 'ukryta-bez-ceny',
            'duration_days' => 1,
            'is_active' => true,
            'start_place_id' => $hub->id,
            'end_place_id' => $torun->id,
        ]);
        EventTemplateStartingPlaceAvailability::create([
            'event_template_id' => $hidden->id,
            'start_place_id' => $gdansk->id,
            'end_place_id' => $hub->id,
            'available' => true,
        ]);

        $response = $this->get('/llms.txt');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('https://bprafa.pl/', $body);
        $this->assertStringContainsString('#### 1-dniowe', $body);
        $this->assertStringContainsString('#### 2-dniowe', $body);
        $this->assertLessThan(
            strpos($body, '#### 2-dniowe'),
            strpos($body, '#### 1-dniowe'),
            'Sekcja 1-dniowe powinna być przed 2-dniowe'
        );
        $this->assertStringContainsString('Jednodniowa wycieczka do Torunia z Gdańska', $body);
        $this->assertStringContainsString('Toruń Stare Miasto', $body);
        $this->assertStringContainsString('https://bprafa.pl/gdansk/1-dniowe/'.$template->id.'/torun-stare-miasto', $body);
        $this->assertStringContainsString('Wycieczki szkolne z Gdańska', $body);
        $this->assertStringNotContainsString('Ukryta bez ceny', $body);
    }

    public function test_builder_cache_can_be_flushed(): void
    {
        $body1 = app(LlmsTxtBuilder::class)->toString();
        $this->assertNotSame('', $body1);

        LlmsTxtBuilder::flushCache();
        $this->assertFalse(Cache::has(LlmsTxtBuilder::CACHE_KEY));
    }
}
