<?php

namespace App\Services;

use App\Models\ContractSetting;
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractAttachmentCatalogService
{
    public const SETTING_DEFAULT_ATTACHMENTS = 'default_attachments';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        $catalog = [];

        $publicFiles = [
            'dokumenty/Warunki-Uczestnictwa-2026.pdf' => 'System / Warunki uczestnictwa 2026',
            'dokumenty/Standardowy-Formularz.pdf' => 'System / Standardowy formularz informacyjny',
            'nnw_ow_rp.pdf' => 'System / Warunki ubezpieczenia NNW - kraj',
            'kl_ow.pdf' => 'System / Warunki ubezpieczenia KL',
            'kr_ow.pdf' => 'System / Warunki ubezpieczenia kosztów rezygnacji',
            'dokumenty/regulamin_przewozu_osób.pdf' => 'System / Regulamin przewozu osób',
            'dokumenty/polityka_rodo.pdf' => 'System / Polityka RODO',
            'dokumenty/wpis_do_rejestru_organizatorow.pdf' => 'System / Wpis do rejestru organizatorów',
        ];

        foreach ($publicFiles as $path => $label) {
            if (Storage::disk('public')->exists($path)) {
                $catalog[$path] = [
                    'label' => $label,
                    'type' => 'public',
                ];
            }
        }

        $workspaceFilesDir = base_path('pliki');

        if (File::isDirectory($workspaceFilesDir)) {
            foreach (File::allFiles($workspaceFilesDir) as $file) {
                $extension = strtolower($file->getExtension());

                if (! $this->isAllowedWorkspaceAttachmentExtension($extension)) {
                    continue;
                }

                $relativePath = str_replace($workspaceFilesDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $publicPath = $this->mapWorkspaceAttachmentToPublicPath($relativePath);

                $catalog[$publicPath] = [
                    'label' => 'Pliki / '.str_replace('/', ' / ', $relativePath),
                    'type' => 'workspace',
                    'source_path' => $file->getPathname(),
                ];
            }
        }

        ksort($catalog);

        return $catalog;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return collect($this->getCatalog())
            ->mapWithKeys(fn (array $item, string $path): array => [$path => $item['label']])
            ->all();
    }

    /**
     * @param  array<int, string>|null  $existingAttachments
     * @return array<int, string>
     */
    public function resolveDefaultSelectedPaths(?int $contractTemplateId = null, ?array $existingAttachments = null): array
    {
        if (filled($existingAttachments)) {
            return $this->filterSelectablePaths($existingAttachments);
        }

        if ($contractTemplateId) {
            $templateDefaults = ContractTemplate::query()
                ->whereKey($contractTemplateId)
                ->value('default_attachments');

            if (filled($templateDefaults)) {
                return $this->filterSelectablePaths((array) $templateDefaults);
            }
        }

        $globalDefaults = ContractSetting::getValue(self::SETTING_DEFAULT_ATTACHMENTS, []);

        return $this->filterSelectablePaths(is_array($globalDefaults) ? $globalDefaults : []);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function filterSelectablePaths(array $paths): array
    {
        $selectable = array_keys($this->getOptions());

        return array_values(array_intersect($paths, $selectable));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function resolveAttachmentsFromFormData(array $data): array
    {
        $selected = $this->materializeSelectedPaths(array_values((array) ($data['selected_attachments'] ?? [])));

        return $this->mergeAttachmentLists(
            array_values((array) ($data['attachments'] ?? [])),
            $selected,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mergeSelectedAttachmentsIntoFormData(array $data): array
    {
        if (empty($data['selected_attachments'])) {
            $data['selected_attachments'] = $this->resolveDefaultSelectedPaths(
                filled($data['contract_template_id'] ?? null) ? (int) $data['contract_template_id'] : null,
            );
        }

        $data['attachments'] = $this->resolveAttachmentsFromFormData($data);
        unset($data['selected_attachments']);

        return $data;
    }

    /**
     * @param  array<int, string>  $uploaded
     * @param  array<int, string>  $selected
     * @return array<int, string>
     */
    public function mergeAttachmentLists(array $uploaded, array $selected): array
    {
        $normalized = array_merge($uploaded, $selected);
        $normalized = array_filter($normalized, fn ($path) => filled($path));

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $selected
     * @return array<int, string>
     */
    public function materializeSelectedPaths(array $selected): array
    {
        $catalog = $this->getCatalog();
        $resolved = [];

        foreach ($selected as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $item = $catalog[$path] ?? null;

            if (($item['type'] ?? null) === 'workspace' && ! empty($item['source_path'])) {
                $this->copyWorkspaceAttachmentToPublic($item['source_path'], $path);
            }

            $resolved[] = $path;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function saveGlobalDefaults(array $paths): void
    {
        ContractSetting::setValue(
            self::SETTING_DEFAULT_ATTACHMENTS,
            $this->filterSelectablePaths($paths),
        );
    }

    protected function copyWorkspaceAttachmentToPublic(string $sourcePath, string $destinationPath): void
    {
        if (Storage::disk('public')->exists($destinationPath)) {
            return;
        }

        Storage::disk('public')->put($destinationPath, File::get($sourcePath));
    }

    protected function isAllowedWorkspaceAttachmentExtension(string $extension): bool
    {
        return in_array($extension, ['pdf', 'doc', 'docx', 'rtf', 'txt', 'jpg', 'jpeg', 'png', 'webp'], true);
    }

    protected function mapWorkspaceAttachmentToPublicPath(string $relativePath): string
    {
        $relativePath = trim($relativePath, '/');
        $directory = str_replace('\\', '/', dirname($relativePath));
        $directory = $directory === '.' ? '' : collect(explode('/', $directory))
            ->filter(fn ($segment) => $segment !== '')
            ->map(fn ($segment) => Str::slug($segment))
            ->implode('/');

        $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        $slug = Str::slug($fileName);
        $hash = substr(sha1($relativePath), 0, 8);

        $targetName = trim($slug !== '' ? $slug : 'plik', '-').'-'.$hash.($extension !== '' ? '.'.$extension : '');

        return trim('event-agreements/library/'.($directory !== '' ? $directory.'/' : '').$targetName, '/');
    }
}
