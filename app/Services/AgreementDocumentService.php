<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Support\DomPdfFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AgreementDocumentService
{
    public function renderPdfBytes(Contract|EventAgreement $agreement): ?string
    {
        $agreement->loadMissing([
            'event',
            'orderingParties.contractor',
            'paymentSchedules',
        ]);

        if (method_exists($agreement, 'resolveAgreementPdfBytes')) {
            $uploaded = $agreement->resolveAgreementPdfBytes();

            if (filled($uploaded)) {
                return $uploaded;
            }
        }

        if (blank($agreement->agreement_body)) {
            return null;
        }

        $html = view('pdf.agreement', [
            'agreement' => $agreement,
            'agreementBody' => (string) $agreement->agreement_body,
            'groupPricing' => app(ContractGroupPricingService::class)->presentationFor($agreement),
        ])->render();

        return DomPdfFactory::loadHTML($html)->output();
    }

    /**
     * ZIP: umowa.pdf + załączniki (katalogowe / wgrane).
     *
     * @return array{bytes: string, filename: string}|null
     */
    public function renderPdfPackageZip(Contract|EventAgreement $agreement): ?array
    {
        $pdfBytes = $this->renderPdfBytes($agreement);

        if ($pdfBytes === null) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'umowa_zip_');
        if ($tmp === false) {
            return null;
        }

        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $agreementName = $this->safeFilename(
            'umowa-'.($agreement->contract_number ?? $agreement->agreement_number ?? $agreement->id)
        ).'.pdf';

        $zip->addFromString($agreementName, $pdfBytes);

        foreach ($this->resolveAttachmentFiles($agreement) as $file) {
            $zip->addFile($file['absolute_path'], 'zalaczniki/'.$file['zip_name']);
        }

        $zip->close();

        $bytes = file_get_contents($zipPath) ?: null;
        @unlink($zipPath);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'filename' => $this->safeFilename(
                'umowa-pakiet-'.($agreement->contract_number ?? $agreement->agreement_number ?? $agreement->id)
            ).'.zip',
        ];
    }

    /**
     * @return list<array{absolute_path: string, zip_name: string}>
     */
    public function resolveAttachmentFiles(Contract|EventAgreement $agreement): array
    {
        $paths = array_values(array_filter((array) ($agreement->attachments ?? [])));
        $files = [];
        $usedNames = [];

        foreach ($paths as $index => $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $absolute = $this->resolveAbsoluteAttachmentPath($path);
            if ($absolute === null) {
                continue;
            }

            $base = $this->safeFilename(pathinfo($path, PATHINFO_FILENAME) ?: 'zalacznik');
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $name = $base.($ext !== '' ? '.'.$ext : '');

            if (isset($usedNames[$name])) {
                $name = $base.'-'.($index + 1).($ext !== '' ? '.'.$ext : '');
            }
            $usedNames[$name] = true;

            $files[] = [
                'absolute_path' => $absolute,
                'zip_name' => $name,
            ];
        }

        return $files;
    }

    private function resolveAbsoluteAttachmentPath(string $path): ?string
    {
        if (is_file($path)) {
            return $path;
        }

        $public = Storage::disk('public')->path($path);
        if (is_file($public)) {
            return $public;
        }

        $local = Storage::disk('local')->path($path);
        if (is_file($local)) {
            return $local;
        }

        return null;
    }

    private function safeFilename(string $value): string
    {
        $value = Str::of($value)
            ->replace(['/', '\\', ' '], ['-', '-', '-'])
            ->toString();

        $value = preg_replace('/[^A-Za-z0-9\-_.ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+/u', '-', $value) ?? $value;
        $value = trim($value, '-_.');

        return $value !== '' ? $value : 'dokument';
    }
}
