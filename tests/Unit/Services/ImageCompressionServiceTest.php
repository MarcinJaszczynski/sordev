<?php

namespace Tests\Unit\Services;

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageCompressionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_compress_and_store_preserves_landscape_aspect_ratio(): void
    {
        $file = UploadedFile::fake()->image('wide.jpg', 2400, 1200);

        $result = ImageCompressionService::compressAndStore($file, 'public', 'event-templates');

        $manager = new ImageManager(new Driver);
        $stored = $manager->read(Storage::disk('public')->get($result['original']));

        $this->assertSame(1920, $stored->width());
        $this->assertSame(960, $stored->height());
        $this->assertSame(1920, $result['dimensions']['width']);
        $this->assertSame(960, $result['dimensions']['height']);
    }

    public function test_compress_and_store_preserves_portrait_aspect_ratio(): void
    {
        $file = UploadedFile::fake()->image('tall.jpg', 1200, 2400);

        $result = ImageCompressionService::compressAndStore($file, 'public', 'event-templates');

        $manager = new ImageManager(new Driver);
        $stored = $manager->read(Storage::disk('public')->get($result['original']));

        $this->assertSame(540, $stored->width());
        $this->assertSame(1080, $stored->height());
    }

    public function test_thumbnail_is_square_cover_centered(): void
    {
        $manager = new ImageManager(new Driver);
        $canvas = $manager->create(400, 200)->fill('ff0000');
        $canvas->drawRectangle(150, 0, function ($rectangle) {
            $rectangle->size(100, 200);
            $rectangle->background('00ff00');
        });

        $tmp = tempnam(sys_get_temp_dir(), 'img_').'.jpg';
        file_put_contents($tmp, (string) $canvas->toJpeg(90));

        try {
            $file = new UploadedFile($tmp, 'bands.jpg', 'image/jpeg', null, true);
            $result = ImageCompressionService::compressAndStore($file, 'public', 'event-templates');

            $thumb = $manager->read(Storage::disk('public')->get($result['thumbnail']));

            $this->assertSame(ImageCompressionService::THUMBNAIL_SIZE, $thumb->width());
            $this->assertSame(ImageCompressionService::THUMBNAIL_SIZE, $thumb->height());

            $center = $thumb->pickColor(
                (int) floor(ImageCompressionService::THUMBNAIL_SIZE / 2),
                (int) floor(ImageCompressionService::THUMBNAIL_SIZE / 2)
            );

            $this->assertLessThan(40, $center->red()->value());
            $this->assertGreaterThan(200, $center->green()->value());
            $this->assertLessThan(40, $center->blue()->value());
        } finally {
            @unlink($tmp);
        }
    }

    public function test_compress_existing_image_regenerates_thumbnail_with_cover(): void
    {
        $source = UploadedFile::fake()->image('existing.jpg', 800, 400);
        Storage::disk('public')->putFileAs('event-templates', $source, 'existing.jpg');

        $result = ImageCompressionService::compressExistingImage('public', 'event-templates/existing.jpg', true);

        $manager = new ImageManager(new Driver);
        $stored = $manager->read(Storage::disk('public')->get('event-templates/existing.jpg'));
        $thumb = $manager->read(Storage::disk('public')->get($result['thumbnail']));

        $this->assertSame(800, $stored->width());
        $this->assertSame(400, $stored->height());
        $this->assertSame(ImageCompressionService::THUMBNAIL_SIZE, $thumb->width());
        $this->assertSame(ImageCompressionService::THUMBNAIL_SIZE, $thumb->height());
    }

    public function test_purge_thumbnails_removes_thumbs_under_directory(): void
    {
        Storage::disk('public')->put('event-templates/full.jpg', 'full');
        Storage::disk('public')->put('event-templates/thumbs/full.jpg', 'old-thumb');
        Storage::disk('public')->put('event-templates/gallery/thumbs/g.jpg', 'old-gallery-thumb');

        $deleted = ImageCompressionService::purgeThumbnails('public', 'event-templates');

        $this->assertSame(2, $deleted);
        Storage::disk('public')->assertMissing('event-templates/thumbs/full.jpg');
        Storage::disk('public')->assertMissing('event-templates/gallery/thumbs/g.jpg');
        Storage::disk('public')->assertExists('event-templates/full.jpg');
    }

    public function test_compress_existing_skips_webp_when_primary_sibling_exists(): void
    {
        $source = UploadedFile::fake()->image('offer.jpg', 800, 400);
        Storage::disk('public')->putFileAs('event-templates', $source, 'offer.jpg');
        Storage::disk('public')->put('event-templates/offer.webp', 'fake-webp-bytes');

        $results = ImageCompressionService::compressExistingImages('public', 'event-templates', true);

        $this->assertCount(1, $results);
        $this->assertSame('event-templates/offer.jpg', $results[0]['file']);
        Storage::disk('public')->assertExists('event-templates/thumbs/offer.jpg');
    }
}
