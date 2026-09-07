<?php

use App\Models\EventTemplate;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('starting place select options returns only starting places', function () {
    $start = Place::query()->create(['name' => 'Kalisz', 'starting_place' => true]);
    $other = Place::query()->create(['name' => 'Wilno', 'starting_place' => false]);

    $options = Place::startingPlaceSelectOptions();

    expect($options)->toHaveKey($start->id)
        ->and($options)->not->toHaveKey($other->id);
});

test('starting place select options can include legacy selected place', function () {
    $legacy = Place::query()->create(['name' => 'Stare miasto', 'starting_place' => false]);

    $options = Place::startingPlaceSelectOptions($legacy->id);

    expect($options)->toHaveKey($legacy->id);
});

test('template start place options respect availability for template', function () {
    $kalisz = Place::query()->create(['name' => 'Kalisz', 'starting_place' => true]);
    $poznan = Place::query()->create(['name' => 'Poznań', 'starting_place' => true]);
    $warszawa = Place::query()->create(['name' => 'Warszawa', 'starting_place' => true]);

    $template = EventTemplate::factory()->create();

    EventTemplateStartingPlaceAvailability::query()->create([
        'event_template_id' => $template->id,
        'start_place_id' => $kalisz->id,
        'end_place_id' => $kalisz->id,
        'available' => true,
    ]);

    EventTemplateStartingPlaceAvailability::query()->create([
        'event_template_id' => $template->id,
        'start_place_id' => $poznan->id,
        'end_place_id' => $poznan->id,
        'available' => false,
    ]);

    $options = Place::startingPlaceSelectOptionsForTemplate($template->id);

    expect($options)->toHaveKey($kalisz->id)
        ->and($options)->not->toHaveKey($poznan->id)
        ->and($options)->not->toHaveKey($warszawa->id);
});

test('template without availability returns empty start place options', function () {
    $kalisz = Place::query()->create(['name' => 'Kalisz', 'starting_place' => true]);
    $poznan = Place::query()->create(['name' => 'Poznań', 'starting_place' => true]);

    $template = EventTemplate::factory()->create();

    $options = Place::startingPlaceSelectOptionsForTemplate($template->id);

    expect($options)->not->toHaveKey($kalisz->id)
        ->and($options)->not->toHaveKey($poznan->id)
        ->and($options)->toBeEmpty();
});

test('template start place options can include legacy selected place outside availability', function () {
    $kalisz = Place::query()->create(['name' => 'Kalisz', 'starting_place' => true]);
    $legacy = Place::query()->create(['name' => 'Stare miasto', 'starting_place' => true]);

    $template = EventTemplate::factory()->create();

    EventTemplateStartingPlaceAvailability::query()->create([
        'event_template_id' => $template->id,
        'start_place_id' => $kalisz->id,
        'end_place_id' => $kalisz->id,
        'available' => true,
    ]);

    $options = Place::startingPlaceSelectOptionsForTemplate($template->id, $legacy->id);

    expect($options)->toHaveKey($kalisz->id)
        ->and($options)->toHaveKey($legacy->id);
});

test('place search select options match only by name not description', function () {
    $krakow = Place::query()->create([
        'name' => 'Kraków',
        'description' => 'Stolica Małopolski',
        'starting_place' => false,
    ]);
    $bobolice = Place::query()->create([
        'name' => 'Bobolice',
        'description' => 'Zamek niedaleko Krakowa',
        'starting_place' => false,
    ]);

    $options = Place::searchSelectOptions('Kraków');

    expect($options)->toHaveKey($krakow->id)
        ->and($options)->not->toHaveKey($bobolice->id)
        ->and($options[$krakow->id])->toBe('Kraków');
});

test('place search select options disambiguate duplicate names', function () {
    $first = Place::query()->create(['name' => 'Rzeszów', 'starting_place' => false]);
    $second = Place::query()->create(['name' => 'Rzeszów', 'starting_place' => false]);

    $options = Place::searchSelectOptions('Rzeszów');

    expect($options)->toHaveKey($first->id)
        ->and($options)->toHaveKey($second->id)
        ->and($options[$first->id])->toBe('Rzeszów (#'.$first->id.')')
        ->and($options[$second->id])->toBe('Rzeszów (#'.$second->id.')');

    expect(Place::optionLabel($first->id))->toBe('Rzeszów (#'.$first->id.')');
});

test('place search prefers prefix matches over contains', function () {
    $exact = Place::query()->create(['name' => 'Testowo', 'starting_place' => false]);
    $prefix = Place::query()->create(['name' => 'Testowo Górne', 'starting_place' => false]);
    $contains = Place::query()->create(['name' => 'Stare Testowo', 'starting_place' => false]);

    $options = Place::searchSelectOptions('Testowo');
    $ids = array_keys($options);

    expect($ids[0])->toBe($exact->id)
        ->and(array_search($prefix->id, $ids, true))
        ->toBeLessThan(array_search($contains->id, $ids, true));
});

test('place search for krakow does not return kalisz or kadzidlo', function () {
    Place::query()->create(['name' => 'Kraków', 'starting_place' => false]);
    Place::query()->create(['name' => 'Kalisz', 'starting_place' => false]);
    Place::query()->create(['name' => 'Kadzidło', 'starting_place' => false]);

    $options = Place::searchSelectOptions('krakow');

    expect($options)->toHaveCount(1)
        ->and(array_values($options))->toBe(['Kraków']);
});

test('place search requires at least two characters', function () {
    Place::query()->create(['name' => 'Kraków', 'starting_place' => false]);

    expect(Place::searchSelectOptions('k'))->toBeEmpty()
        ->and(Place::searchSelectOptions('kr'))->not->toBeEmpty();
});

test('place search select options skip blank names', function () {
    Place::query()->create(['name' => '   ', 'starting_place' => false]);
    $valid = Place::query()->create(['name' => 'Poznań', 'starting_place' => false]);

    $options = Place::searchSelectOptions('Poz');

    expect($options)->toHaveKey($valid->id)
        ->and($options)->not->toContain('')
        ->and(collect($options)->every(fn (string $label): bool => trim($label) !== ''))->toBeTrue();
});
