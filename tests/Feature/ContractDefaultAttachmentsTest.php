<?php

namespace Tests\Feature;

use App\Models\ContractSetting;
use App\Models\ContractTemplate;
use App\Services\ContractAttachmentCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDefaultAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_defaults_are_used_when_no_template_defaults(): void
    {
        $service = app(ContractAttachmentCatalogService::class);
        $available = array_keys($service->getOptions());
        $this->assertNotEmpty($available);

        $configured = array_slice($available, 0, min(2, count($available)));

        ContractSetting::setValue(ContractAttachmentCatalogService::SETTING_DEFAULT_ATTACHMENTS, $configured);

        $defaults = $service->resolveDefaultSelectedPaths();

        foreach ($configured as $path) {
            $this->assertContains($path, $defaults);
        }
    }

    public function test_template_defaults_override_global_defaults(): void
    {
        $service = app(ContractAttachmentCatalogService::class);
        $available = array_keys($service->getOptions());
        $this->assertNotEmpty($available);

        ContractSetting::setValue(ContractAttachmentCatalogService::SETTING_DEFAULT_ATTACHMENTS, [
            $available[0],
        ]);

        $templatePath = $available[count($available) > 1 ? 1 : 0];

        $template = ContractTemplate::create([
            'name' => 'Szablon szkolny',
            'content' => 'Treść umowy',
            'default_attachments' => [$templatePath],
        ]);

        $defaults = $service->resolveDefaultSelectedPaths($template->id);

        $this->assertSame([$templatePath], $defaults);
    }

    public function test_existing_attachments_take_priority_over_defaults(): void
    {
        $existing = ['dokumenty/polityka_rodo.pdf'];

        $defaults = app(ContractAttachmentCatalogService::class)->resolveDefaultSelectedPaths(null, $existing);

        $this->assertSame(
            app(ContractAttachmentCatalogService::class)->filterSelectablePaths($existing),
            $defaults,
        );
    }

    public function test_merge_form_data_applies_defaults_when_none_selected(): void
    {
        $service = app(ContractAttachmentCatalogService::class);
        $available = array_keys($service->getOptions());
        $this->assertNotEmpty($available);

        ContractSetting::setValue(ContractAttachmentCatalogService::SETTING_DEFAULT_ATTACHMENTS, [
            $available[0],
        ]);

        $data = $service->mergeSelectedAttachmentsIntoFormData([
            'selected_attachments' => [],
        ]);

        $this->assertContains($available[0], $data['attachments']);
    }
}
