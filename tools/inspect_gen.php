<?php
$p = 'storage/app/public/event-offers/oferta-imprezy-18-20260817-115018.docx';
$z = new ZipArchive;
$z->open($p);

echo "== Header (clean check) ==\n";
$h = $z->getFromName('word/header1.xml');
echo 'Has DrawingML: ', (str_contains($h, '<w:drawing>') ? 'YES' : 'NO'), "\n";
echo 'No VML: ', (! str_contains($h, '<w:pict>') ? 'YES' : 'NO'), "\n";

echo "== Doc body entity check ==\n";
$d = $z->getFromName('word/document.xml');
echo 'Has raw &nbsp;: ', (preg_match('/&nbsp;/', $d) ? 'YES (BAD)' : 'NO (GOOD)'), "\n";
echo 'All XML well-formed: ';
$allOk = true;
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (preg_match('/\.(xml|rels)$/', $n)) {
        libxml_use_internal_errors(true);
        if (simplexml_load_string($z->getFromName($n)) === false) {
            echo "BAD:$n ";
            $allOk = false;
        }
        libxml_clear_errors();
    }
}
echo ($allOk ? "YES\n" : "\n");

$z->close();
