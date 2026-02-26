<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\EventTemplate;
use App\Models\EventTemplateQty;
use App\Models\EventTemplatePricePerPerson;
use App\Models\Place;
use App\Models\Currency;
use App\Http\Controllers\Front\FrontController;

class PriceResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function resolves_min_pln_local_first_and_latest_per_qty()
    {
        // Build a collection of simple objects representing price rows (avoid DB schema dependency)
        $prices = collect([]);
        // older local price (id=1)
        $prices->push((object)[
            'id' => 1,
            'start_place_id' => 1,
            'event_template_qty_id' => 41,
            'price_per_person' => 300,
            'currency' => (object)['code' => 'PLN', 'symbol' => 'zł', 'name' => 'Polski złoty'],
        ]);
        // newer local price (id=2) -> should be picked as latest per qty for local
        $prices->push((object)[
            'id' => 2,
            'start_place_id' => 1,
            'event_template_qty_id' => 41,
            'price_per_person' => 235,
            'currency' => (object)['code' => 'PLN', 'symbol' => 'zł', 'name' => 'Polski złoty'],
        ]);
        // global other place low price (id=3)
        $prices->push((object)[
            'id' => 3,
            'start_place_id' => 2,
            'event_template_qty_id' => 41,
            'price_per_person' => 190,
            'currency' => (object)['code' => 'PLN', 'symbol' => 'zł', 'name' => 'Polski złoty'],
        ]);

        $ctrl = new FrontController();
        $rm = new \ReflectionMethod(FrontController::class, 'resolveMinPlnFromPrices');
        $rm->setAccessible(true);
        $minLocal = $rm->invoke($ctrl, $prices, 1);
        $minGlobal = $rm->invoke($ctrl, $prices, null);

        $this->assertEquals(235, $minLocal, 'Expected local minimal PLN to be 235 (latest per qty)');
        $this->assertEquals(190, $minGlobal, 'Expected global minimal PLN to be 190');
    }
}
