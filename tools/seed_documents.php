<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DocumentSection;
use App\Models\Document;
use App\Models\DocumentAttachment;

DocumentSection::truncate();
Document::truncate();
DocumentAttachment::truncate();

$sec1 = DocumentSection::create(['title' => 'Warunki uczestnictwa', 'slug' => 'warunki', 'order_number' => 1]);
$sec2 = DocumentSection::create(['title' => 'CEIDG i rejestr', 'slug' => 'ceidg', 'order_number' => 2]);

$doc1 = Document::create(['document_section_id' => $sec1->id, 'title' => 'Warunki uczestnictwa 2025', 'slug' => 'warunki-uczestnictwa-2025', 'excerpt' => 'Warunki uczestnictwa - skrót', 'content' => '<p>Pełne warunki uczestnictwa...</p>', 'order_number' => 1, 'is_published' => true]);
$doc2 = Document::create(['document_section_id' => $sec1->id, 'title' => 'Regulamin przewozu', 'slug' => 'regulamin-przewozu', 'excerpt' => 'Regulamin przewozu - skrót', 'content' => '<p>Treść regulaminu...</p>', 'order_number' => 2, 'is_published' => true]);

DocumentAttachment::create(['document_id' => $doc1->id, 'path' => 'uploads/documents/warunki_uczestnictwa.pdf', 'original_name' => 'warunki_uczestnictwa.pdf', 'mime_type' => 'application/pdf', 'order_number' => 1]);
DocumentAttachment::create(['document_id' => $doc2->id, 'path' => 'uploads/documents/regulamin_przewozu.pdf', 'original_name' => 'regulamin_przewozu.pdf', 'mime_type' => 'application/pdf', 'order_number' => 1]);

echo "Seeded documents\n";
