<?php

namespace Tests\Unit;

use App\Support\AgreementHtml;
use App\Support\DomPdfFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomPdfPolishDiacriticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bold_and_normal_render_polish_diacritics(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 14px; }
b, strong { font-family: DejaVu Sans, sans-serif; font-weight: bold; }
.semi { font-weight: bold; }
.val { font-family: DejaVu Sans, sans-serif; font-weight: bold; }
.driver-hl { font-family: DejaVu Sans, sans-serif; font-weight: bold; }
</style>
</head>
<body>
<p>NORM: Zażółć gęślą jaźń</p>
<p><b>BOLD: Zażółć gęślą jaźń</b></p>
<p class="semi">SEMI: Zażółć gęślą jaźń</p>
<p class="val">VAL: obsługa poniedziałek Jaszczyński</p>
<p class="driver-hl">HL: Paryż dzień płaci</p>
</body>
</html>
HTML;

        $path = storage_path('app/test-diacritics-dompdf.pdf');
        file_put_contents($path, DomPdfFactory::loadHTML($html)->output());

        $text = shell_exec('pdftotext '.escapeshellarg($path).' -') ?: '';

        $this->assertStringContainsString('NORM: Zażółć gęślą jaźń', $text);
        $this->assertStringContainsString('BOLD: Zażółć gęślą jaźń', $text);
        $this->assertStringContainsString('SEMI: Zażółć gęślą jaźń', $text);
        $this->assertStringContainsString('VAL: obsługa poniedziałek Jaszczyński', $text);
        $this->assertStringContainsString('HL: Paryż dzień płaci', $text);
    }

    public function test_package_styles_do_not_use_broken_font_weights(): void
    {
        $css = file_get_contents(resource_path('views/pdf/packages/_styles.blade.php')) ?: '';

        $this->assertStringNotContainsString('font-weight: 600', $css);
        $this->assertStringNotContainsString('font-weight: 500', $css);
        $this->assertStringContainsString('DejaVu Sans', $css);
        $this->assertStringContainsString('table.rows .val', $css);
        $this->assertMatchesRegularExpression('/\.val[^{]*\{[^}]*font-family:\s*DejaVu Sans/s', $css);
    }

    public function test_agreement_html_keeps_bold_tags(): void
    {
        $html = AgreementHtml::sanitize('<p>Start <strong>ważne</strong> koniec</p>');

        $this->assertStringContainsString('<strong>ważne</strong>', $html);
    }
}
