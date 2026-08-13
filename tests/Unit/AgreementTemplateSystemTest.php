<?php

namespace Tests\Unit;

use App\Models\ContractTemplate;
use App\Services\AgreementPlaceholderCatalog;
use App\Services\AgreementTemplateRenderer;
use App\Services\AgreementTransportPayloadResolver;
use App\Support\AgreementHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementTemplateSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_covers_renderer_system_tags(): void
    {
        $catalog = app(AgreementPlaceholderCatalog::class);
        $tags = array_column($catalog->definitions(), 'tag');

        $this->assertContains('[NAZWA_IMPREZY]', $tags);
        $this->assertContains('[ORGANIZATOR_NAZWA]', $tags);
        $this->assertContains('[GODZINA_WYJAZDU]', $tags);
        $this->assertNotEmpty($catalog->helperText());
    }

    public function test_renderer_replaces_system_and_custom_placeholders(): void
    {
        $template = ContractTemplate::create([
            'name' => 'Test',
            'content' => 'Impreza [NAZWA_IMPREZY], start [godzina], firma [ORGANIZATOR_NAZWA]',
            'custom_placeholders' => [
                ['key' => 'godzina', 'label' => 'Godzina', 'default' => '08:00'],
            ],
        ]);

        $rendered = app(AgreementTemplateRenderer::class)->render($template, [
            'event_name' => 'Wycieczka',
            'organizer_name' => 'Firma X',
            'custom_placeholder_values' => [
                'godzina' => '07:30',
            ],
        ]);

        $this->assertSame('Impreza Wycieczka, start 07:30, firma Firma X', $rendered);
    }

    public function test_renderer_uses_custom_default_when_value_missing(): void
    {
        $template = ContractTemplate::create([
            'name' => 'Test default',
            'content' => 'Sala: [sala]',
            'custom_placeholders' => [
                ['key' => 'sala', 'label' => 'Sala', 'default' => 'Aula'],
            ],
        ]);

        $rendered = app(AgreementTemplateRenderer::class)->render($template, []);

        $this->assertSame('Sala: Aula', $rendered);
    }

    public function test_transport_payload_from_event_fields(): void
    {
        $event = \App\Models\Event::factory()->create([
            'departure_time' => '07:45:00',
            'return_time' => '19:15:00',
            'pickup_place_details' => 'Plac Defilad 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
        ]);

        $payload = app(AgreementTransportPayloadResolver::class)->forEvent($event);

        $this->assertSame('Plac Defilad 1', $payload['departure_place']);
        $this->assertSame('07:45', $payload['departure_time']);
        $this->assertSame('19:15', $payload['return_time']);
        $this->assertSame('01.09.2026', $payload['departure_date']);
        $this->assertSame('03.09.2026', $payload['return_date']);
    }

    public function test_template_versioning_deactivates_previous(): void
    {
        $template = ContractTemplate::create([
            'name' => 'Wersjonowany',
            'content' => 'v1',
            'is_active' => true,
        ]);

        $v2 = $template->createNewVersion('Poprawki prawne');

        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->is_active);
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame($template->version_group_id, $v2->version_group_id);
    }

    public function test_options_for_select_filters_by_applies_to(): void
    {
        $groupOnly = ContractTemplate::create([
            'name' => 'Tylko grupowa',
            'content' => 'x',
            'applies_to' => ['group'],
            'is_active' => true,
        ]);

        $individualOnly = ContractTemplate::create([
            'name' => 'Tylko indywidualna',
            'content' => 'y',
            'applies_to' => ['individual'],
            'is_active' => true,
        ]);

        $options = ContractTemplate::optionsForSelect('group');

        $this->assertArrayHasKey($groupOnly->id, $options);
        $this->assertArrayNotHasKey($individualOnly->id, $options);
    }

    public function test_agreement_html_sanitize_strips_scripts(): void
    {
        $html = '<p onclick="alert(1)">OK</p><script>bad()</script>';
        $clean = AgreementHtml::sanitize($html);

        $this->assertStringContainsString('<p>OK</p>', $clean);
        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
    }

    public function test_normalize_content_accepts_array_without_casting_error(): void
    {
        $html = AgreementHtml::normalizeContent([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello [NAZWA_IMPREZY]'],
                    ],
                ],
            ],
        ]);

        $this->assertIsString($html);
        $this->assertStringContainsString('Hello [NAZWA_IMPREZY]', $html);
    }

    public function test_plain_text_to_editor_html_wraps_lines(): void
    {
        $html = AgreementHtml::plainTextToEditorHtml("Linia 1\nLinia 2");

        $this->assertStringContainsString('<p>Linia 1</p>', $html);
        $this->assertStringContainsString('<p>Linia 2</p>', $html);
    }

    public function test_html_to_editable_plain_text(): void
    {
        $plain = AgreementHtml::toEditablePlainText('<p>Linia 1</p><p>Linia 2</p>');

        $this->assertStringContainsString('Linia 1', $plain);
        $this->assertStringContainsString('Linia 2', $plain);
        $this->assertStringNotContainsString('<p>', $plain);
    }
}
