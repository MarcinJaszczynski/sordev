<?php

use App\Models\EventTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('prefers preview image path for template lists', function () {
    $template = EventTemplate::factory()->create([
        'featured_image' => 'event-templates/sample.jpg',
    ]);

    expect($template->preview_image_path)->toBe('event-templates/thumbs/sample.jpg');
    expect($template->full_image_path)->toBe('event-templates/sample.jpg');
});

it('renders preview urls in the frontend package list partial', function () {
    Storage::fake('public');

    $template = EventTemplate::factory()->create([
        'featured_image' => 'event-templates/listing.jpg',
    ]);

    Storage::disk('public')->put('event-templates/listing.jpg', 'fake-image');
    Storage::disk('public')->put('event-templates/thumbs/listing.jpg', 'fake-thumb');

    $html = view('front.partials.packages-items', [
        'eventTemplate' => collect([$template]),
        'start_place_id' => null,
    ])->render();

    expect($html)->toContain('/storage/event-templates/thumbs/listing.jpg');
});

it('falls back to full image url when preview file is missing', function () {
    Storage::fake('public');

    $template = EventTemplate::factory()->create([
        'featured_image' => 'event-templates/listing.jpg',
    ]);

    Storage::disk('public')->put('event-templates/listing.jpg', 'fake-image');

    expect($template->preview_image_url)->toBe('/storage/event-templates/listing.jpg');
    expect($template->full_image_url)->toBe('/storage/event-templates/listing.jpg');
});

it('generates missing previews for existing public images', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('sample.jpg', 1600, 1200);
    Storage::disk('public')->putFileAs('event-templates', $image, 'sample.jpg');

    $this->artisan('images:generate-previews', [
        '--disk' => 'public',
        '--directory' => 'event-templates',
    ])->assertExitCode(0);

    Storage::disk('public')->assertExists('event-templates/thumbs/sample.jpg');
    Storage::disk('public')->assertExists('event-templates/sample.webp');

    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver);
    $stored = $manager->read(Storage::disk('public')->get('event-templates/sample.jpg'));
    $thumb = $manager->read(Storage::disk('public')->get('event-templates/thumbs/sample.jpg'));

    expect($stored->width())->toBe(1440)
        ->and($stored->height())->toBe(1080)
        ->and($thumb->width())->toBe(\App\Services\ImageCompressionService::THUMBNAIL_SIZE)
        ->and($thumb->height())->toBe(\App\Services\ImageCompressionService::THUMBNAIL_SIZE);
});
