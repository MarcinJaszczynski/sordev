<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Help;

use App\Filament\Actions\HelpArticleAction;
use App\Support\Help\HelpCatalog;
use Tests\TestCase;

class HelpCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HelpCatalog::clearCache();
    }

    public function test_admin_catalog_contains_core_scenarios(): void
    {
        $catalog = app(HelpCatalog::class);
        $slugs = array_map(fn ($a) => $a->slug, $catalog->forPanel(HelpCatalog::PANEL_ADMIN));

        $this->assertContains('sciezka-imprezy', $slugs);
        $this->assertContains('rozliczenie-imprezy', $slugs);
        $this->assertContains('wplaty-i-linki', $slugs);
    }

    public function test_search_finds_payment_article(): void
    {
        $catalog = app(HelpCatalog::class);
        $results = $catalog->search(HelpCatalog::PANEL_ADMIN, 'wpłaty');
        $slugs = array_map(fn ($a) => $a->slug, $results);

        $this->assertContains('wplaty-i-linki', $slugs);
    }

    public function test_markdown_renders_safely(): void
    {
        $catalog = app(HelpCatalog::class);
        $article = $catalog->find(HelpCatalog::PANEL_ADMIN, 'sciezka-imprezy');
        $this->assertNotNull($article);

        $html = $catalog->renderHtml($article);
        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_help_article_urls_point_to_panel_help(): void
    {
        $this->assertStringContainsString('/admin/help?article=rozliczenie-imprezy', HelpArticleAction::url('rozliczenie-imprezy', 'admin'));
        $this->assertStringContainsString('/portal/help?article=jak-zaplacic', HelpArticleAction::url('jak-zaplacic', 'portal'));
        $this->assertStringContainsString('/pilot/help?article=zaliczka-i-rozliczenie', HelpArticleAction::url('zaliczka-i-rozliczenie', 'pilot'));
    }

    public function test_portal_and_pilot_have_short_guides(): void
    {
        $catalog = app(HelpCatalog::class);

        $portal = $catalog->forPanel(HelpCatalog::PANEL_PORTAL);
        $pilot = $catalog->forPanel(HelpCatalog::PANEL_PILOT);

        $this->assertGreaterThanOrEqual(4, count($portal));
        $this->assertGreaterThanOrEqual(4, count($pilot));
        $this->assertContains('jak-zaplacic', array_map(fn ($a) => $a->slug, $portal));
        $this->assertContains('zaliczka-i-rozliczenie', array_map(fn ($a) => $a->slug, $pilot));
    }
}
