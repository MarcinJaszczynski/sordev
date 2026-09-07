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

test('search select options finds places by name without requiring full preload', function () {
    Place::query()->create(['name' => 'Wilno Stare Miasto', 'starting_place' => false]);
    Place::query()->create(['name' => 'Kalisz', 'starting_place' => true]);
    $target = Place::query()->create(['name' => 'Kraków Rynek', 'starting_place' => false]);

    $options = Place::searchSelectOptions('Krak', 50);

    expect($options)->toHaveKey($target->id)
        ->and($options)->not->toHaveKey(
            Place::query()->where('name', 'Kalisz')->value('id')
        );
});

test('search select options keeps currently selected place even if outside result set', function () {
    $selected = Place::query()->create(['name' => 'Zakopane', 'starting_place' => false]);
    Place::query()->create(['name' => 'Gdańsk', 'starting_place' => false]);

    $options = Place::searchSelectOptions('Gdań', 50, $selected->id);

    expect($options)->toHaveKey($selected->id)
        ->and($options[$selected->id])->toBe('Zakopane');
});
