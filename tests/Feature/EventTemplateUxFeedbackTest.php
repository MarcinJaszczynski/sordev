<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventTemplateResource\Pages\EventTemplateCalculation;
use App\Filament\Resources\EventTemplateResource\Pages\ManageTemplateQtyVariants;
use App\Livewire\EventProgramTreeEditor;
use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateProgramPoint;
use App\Models\EventTemplateQty;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Place;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTemplateUxFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'view event_template',
            'edit event_template',
            'edit event_template_program',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
    }

    public function test_edit_form_uses_polish_description_labels(): void
    {
        $resourceFile = file_get_contents(app_path('Filament/Resources/EventTemplateResource.php'));

        $this->assertStringContainsString("->label('Opis wydarzenia')", $resourceFile);
        $this->assertStringContainsString("->label('Opis dla biura')", $resourceFile);
        $this->assertStringContainsString("->label('Opis SEO')", $resourceFile);
        $this->assertStringContainsString("->label('Otwórz bibliotekę Multimedia')", $resourceFile);
        $this->assertStringNotContainsString("TiptapEditor::make('event_description'),", $resourceFile);
    }

    public function test_qty_variants_navigation_label_is_quantities_not_prices(): void
    {
        $this->assertSame('Warianty ilości', ManageTemplateQtyVariants::getNavigationLabel());
    }

    public function test_program_point_search_keeps_selected_name_after_clearing_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create(['duration_days' => 2]);
        $point = EventTemplateProgramPoint::factory()->create([
            'name' => 'Zwiedzanie Wawelu XYZ',
            'description' => '<p>Opis testowy</p>',
        ]);

        Livewire::test(EventProgramTreeEditor::class, ['eventTemplate' => $template])
            ->call('showAddModal')
            ->set('searchProgramPoint', 'Wawelu')
            ->call('selectProgramPoint', $point->id)
            ->assertSet('modalData.program_point_id', $point->id)
            ->assertSet('searchProgramPoint', '')
            ->assertSet('selectedProgramPointName', 'Zwiedzanie Wawelu XYZ')
            ->assertSee('Wybrano:')
            ->assertSee('Zwiedzanie Wawelu XYZ');
    }

    public function test_calculation_page_shows_start_place_selector(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view event_template', 'edit event_template']);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $warsaw = Place::factory()->starting()->create(['name' => 'Warszawa Test']);
        $gdansk = Place::factory()->starting()->create(['name' => 'Gdańsk Test']);
        $programStart = Place::factory()->create(['name' => 'Kraków Start']);
        $programEnd = Place::factory()->create(['name' => 'Kraków End']);

        $template = EventTemplate::factory()->create([
            'start_place_id' => $programStart->id,
            'end_place_id' => $programEnd->id,
            'program_km' => 10,
        ]);

        foreach ([$warsaw, $gdansk] as $place) {
            EventTemplateStartingPlaceAvailability::query()->create([
                'event_template_id' => $template->id,
                'start_place_id' => $place->id,
                'end_place_id' => $programStart->id,
                'available' => true,
            ]);
        }

        Livewire::test(EventTemplateCalculation::class, ['record' => $template->id])
            ->assertSee('Miejsce startu (lokalizacja wyjazdu)')
            ->assertSee('Warszawa Test')
            ->assertSee('Gdańsk Test')
            ->assertSee('Wybierz miejsce startu powyżej');
    }

    public function test_public_package_shows_edit_template_link_for_staff(): void
    {
        $user = User::factory()->create();
        $user->assignRole('biuro');
        $user->givePermissionTo(['view event_template', 'edit event_template']);
        $this->actingAs($user);

        $start = Place::factory()->starting()->create(['name' => 'Warszawa']);
        $template = $this->createPubliclyAvailableTemplate($start, [
            'name' => 'Oferta testowa UX',
            'slug' => 'oferta-testowa-ux',
        ]);

        $response = $this->withCookie('start_place_id', (string) $start->id)
            ->get($template->prettyUrl($start->id));

        $response->assertOk();
        $response->assertSee('Przejdź do szablonu');
        $response->assertSee(route('filament.admin.resources.event-templates.edit', ['record' => $template->id]), false);
    }

    public function test_public_package_hides_edit_template_link_for_guests(): void
    {
        $start = Place::factory()->starting()->create(['name' => 'Warszawa']);
        $template = $this->createPubliclyAvailableTemplate($start, [
            'name' => 'Oferta gościa',
            'slug' => 'oferta-goscia',
        ]);

        $response = $this->withCookie('start_place_id', (string) $start->id)
            ->get($template->prettyUrl($start->id));

        $response->assertOk();
        $response->assertDontSee('Przejdź do szablonu');
    }

    public function test_offer_preview_prefers_warsaw_and_builds_pretty_url(): void
    {
        $warsaw = Place::factory()->starting()->create(['name' => 'Warszawa']);
        $gdansk = Place::factory()->starting()->create(['name' => 'Gdańsk']);
        $programStart = Place::factory()->create(['name' => 'Hub']);

        $template = EventTemplate::factory()->create([
            'name' => 'Podlasie preview',
            'slug' => 'podlasie-preview',
            'duration_days' => 3,
            'start_place_id' => $programStart->id,
            'is_active' => true,
        ]);

        foreach ([$gdansk, $warsaw] as $place) {
            EventTemplateStartingPlaceAvailability::query()->create([
                'event_template_id' => $template->id,
                'start_place_id' => $place->id,
                'end_place_id' => $programStart->id,
                'available' => true,
            ]);
        }

        $this->assertSame($warsaw->id, \App\Support\EventTemplateOfferPreview::defaultStartPlaceId($template));

        $url = \App\Support\EventTemplateOfferPreview::url($template, $warsaw->id);

        $this->assertStringContainsString('/warszawa/3-dniowe/'.$template->id.'/podlasie-preview', $url);
    }

    public function test_edit_template_preview_offer_action_accepts_start_place(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view event_template', 'edit event_template']);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $warsaw = Place::factory()->starting()->create(['name' => 'Warszawa']);
        $gdansk = Place::factory()->starting()->create(['name' => 'Gdańsk']);
        $programStart = Place::factory()->create(['name' => 'Hub']);

        $template = EventTemplate::factory()->create([
            'name' => 'Oferta z miejscem',
            'slug' => 'oferta-z-miejscem',
            'duration_days' => 3,
            'start_place_id' => $programStart->id,
            'is_active' => true,
        ]);

        foreach ([$warsaw, $gdansk] as $place) {
            EventTemplateStartingPlaceAvailability::query()->create([
                'event_template_id' => $template->id,
                'start_place_id' => $place->id,
                'end_place_id' => $programStart->id,
                'available' => true,
            ]);
        }

        Livewire::test(\App\Filament\Resources\EventTemplateResource\Pages\EditEventTemplate::class, [
            'record' => $template->id,
        ])
            ->assertActionExists('preview_offer')
            ->callAction('preview_offer', data: [
                'start_place_id' => $gdansk->id,
            ])
            ->assertHasNoActionErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPubliclyAvailableTemplate(Place $start, array $overrides = []): EventTemplate
    {
        $template = EventTemplate::factory()->create(array_merge([
            'is_active' => true,
            'duration_days' => 2,
            'start_place_id' => $start->id,
        ], $overrides));

        EventTemplateStartingPlaceAvailability::query()->create([
            'event_template_id' => $template->id,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'available' => true,
        ]);

        $qty = EventTemplateQty::query()->firstOrCreate(
            ['qty' => 40],
            ['gratis' => 3, 'staff' => 1, 'driver' => 1]
        );

        $pln = Currency::factory()->create([
            'code' => 'PLN',
            'symbol' => 'PLN',
            'name' => 'Polski złoty',
        ]);

        EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $pln->id,
            'start_place_id' => $start->id,
            'price_per_person' => 350,
        ]);

        return $template;
    }
}
