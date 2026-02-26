<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\EventTemplate;
use App\Models\Currency;
use App\Models\EventTemplateQty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class WordOfferExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function download_starts_with_zip_signature()
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'slug' => 'test-template',
            'duration_days' => 2,
            'name' => 'Test Template',
        ]);

        $qtyVariant = EventTemplateQty::factory()->create([
            'qty' => 40,
        ]);

        $currency = Currency::factory()->create([
            'code' => 'PLN',
            'symbol' => 'PLN',
            'name' => 'Polski złoty',
        ]);

        $template->pricesPerPerson()->create([
            'start_place_id' => null,
            'event_template_qty_id' => $qtyVariant->id,
            'price_per_person' => 100,
            'currency_id' => $currency->id,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('package.pretty.word', [
            'regionSlug' => 'region',
            'dayLength' => '2-dniowe',
            'id' => $template->id,
            'slug' => 'test-template',
        ]), [
            'organization_name' => 'Szkoła',
        ]);

        $response->assertOk();

        /** @var BinaryFileResponse $baseResponse */
        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $downloadedPath = $baseResponse->getFile()->getPathname();
        $content = file_get_contents($downloadedPath);

        $this->assertNotFalse($content, 'Unable to read downloaded file');
        $this->assertSame('PK', substr($content, 0, 2), 'File does not start with ZIP signature');

        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension not available to validate DOCX contents.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($downloadedPath);
        $this->assertSame(true, $openResult, 'Generated DOCX cannot be opened by ZipArchive.');
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'), 'DOCX missing [Content_Types].xml entry.');
        $this->assertNotFalse($zip->locateName('word/document.xml'), 'DOCX missing main document part.');
        $zip->close();

        @unlink($downloadedPath);
    }
}
