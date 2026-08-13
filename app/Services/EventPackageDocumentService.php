<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventPackageDocument;
use App\Services\Documents\DriverInfoDataBuilder;
use App\Support\DomPdfFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Edycja i rozwiązywanie pakietów PDF imprezy (wzorzec: szkic → podgląd → PDF).
 */
final class EventPackageDocumentService
{
    public function __construct(
        private readonly EventPrintPdfDataFactory $dataFactory,
        private readonly DriverInfoDataBuilder $driverInfoBuilder,
    ) {}

    public function find(Event $event, string $audience): ?EventPackageDocument
    {
        $this->assertAudience($audience);

        return EventPackageDocument::query()
            ->where('event_id', $event->id)
            ->where('audience', $audience)
            ->first();
    }

    public function getOrCreate(Event $event, string $audience): EventPackageDocument
    {
        $this->assertAudience($audience);

        return EventPackageDocument::query()->firstOrCreate(
            [
                'event_id' => $event->id,
                'audience' => $audience,
            ],
            [
                'edit_mode' => EventPackageDocument::EDIT_LIVE,
                'status' => EventPackageDocument::STATUS_DRAFT,
                'overrides' => [],
            ]
        );
    }

    /**
     * @param  array{
     *     edit_mode?: string,
     *     status?: string,
     *     intro_html?: ?string,
     *     extra_notes_html?: ?string,
     *     hide_sections?: list<string>|null,
     *     notes?: ?string,
     *     upload_path?: ?string,
     *     frozen_html?: ?string
     * }  $data
     */
    public function saveDraft(Event $event, string $audience, array $data): EventPackageDocument
    {
        $package = $this->getOrCreate($event, $audience);

        $editMode = (string) ($data['edit_mode'] ?? $package->edit_mode ?: EventPackageDocument::EDIT_OVERRIDES);
        if (! array_key_exists($editMode, EventPackageDocument::$editModeLabels)) {
            $editMode = EventPackageDocument::EDIT_OVERRIDES;
        }

        $overrides = $package->normalizedOverrides();
        if (array_key_exists('intro_html', $data)) {
            $overrides['intro_html'] = filled($data['intro_html'] ?? null) ? (string) $data['intro_html'] : null;
        }
        if (array_key_exists('extra_notes_html', $data)) {
            $overrides['extra_notes_html'] = filled($data['extra_notes_html'] ?? null)
                ? (string) $data['extra_notes_html']
                : null;
        }
        if (array_key_exists('hide_sections', $data)) {
            $overrides['hide_sections'] = array_values(array_filter(
                array_map('strval', $data['hide_sections'] ?? []),
                fn (string $key): bool => array_key_exists($key, EventPackageDocument::$hideSectionLabels)
            ));
        }

        $uploadPath = $package->upload_path;
        if (array_key_exists('upload_path', $data)) {
            $upload = $data['upload_path'];
            if (is_array($upload)) {
                $upload = collect($upload)->filter()->first();
            }
            $uploadPath = filled($upload) ? (string) $upload : null;
        }

        $package->fill([
            'edit_mode' => $editMode,
            'status' => ($data['status'] ?? null) === EventPackageDocument::STATUS_READY
                ? EventPackageDocument::STATUS_READY
                : EventPackageDocument::STATUS_DRAFT,
            'overrides' => $overrides,
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $package->notes,
            'upload_path' => $uploadPath,
            'frozen_html' => array_key_exists('frozen_html', $data)
                ? ($data['frozen_html'] ?: null)
                : $package->frozen_html,
        ]);

        if ($editMode === EventPackageDocument::EDIT_FROZEN && blank($package->frozen_html)) {
            $package->frozen_html = $this->renderLiveHtml($event, $audience, $package);
            $package->generated_at = now();
        }

        if ($editMode !== EventPackageDocument::EDIT_FROZEN) {
            // Tryb nie-frozen: snapshot nie jest źródłem prawdy.
            if ($editMode !== EventPackageDocument::EDIT_UPLOAD) {
                $package->frozen_html = null;
            }
        }

        $package->save();

        return $package->fresh();
    }

    public function freezeFromLive(Event $event, string $audience): EventPackageDocument
    {
        $package = $this->getOrCreate($event, $audience);
        $package->frozen_html = $this->renderLiveHtml($event, $audience, $package);
        $package->edit_mode = EventPackageDocument::EDIT_FROZEN;
        $package->generated_at = now();
        $package->save();

        return $package->fresh();
    }

    public function refreshToLive(Event $event, string $audience): EventPackageDocument
    {
        $package = $this->getOrCreate($event, $audience);
        $package->edit_mode = EventPackageDocument::EDIT_LIVE;
        $package->frozen_html = null;
        $package->status = EventPackageDocument::STATUS_DRAFT;
        $package->generated_at = null;
        $package->save();

        return $package->fresh();
    }

    public function finalize(Event $event, string $audience): EventPackageDocument
    {
        $package = $this->getOrCreate($event, $audience);
        $package->status = EventPackageDocument::STATUS_READY;
        $package->generated_at = now();
        $package->save();

        return $package->fresh();
    }

    /**
     * @return array{
     *     binary: string,
     *     filename: string,
     *     attachedFiles: Collection,
     *     package: ?EventPackageDocument
     * }
     */
    public function resolveDownload(Event $event, string $audience): array
    {
        $this->assertAudience($audience);
        $package = $this->find($event, $audience);

        if ($package?->usesUploadedPdf()) {
            $path = $package->resolveUploadAbsolutePath();
            if ($path === null) {
                throw new InvalidArgumentException('Brak wgranego pliku PDF dla tego pakietu.');
            }

            return [
                'binary' => (string) file_get_contents($path),
                'filename' => $this->filename($event, $audience),
                'attachedFiles' => collect(),
                'package' => $package,
            ];
        }

        if ($package?->usesFrozenHtml()) {
            return [
                'binary' => DomPdfFactory::loadHTML((string) $package->frozen_html)->output(),
                'filename' => $this->filename($event, $audience),
                'attachedFiles' => collect(),
                'package' => $package,
            ];
        }

        $data = $this->buildViewData($event, $audience, $package);
        $view = $this->viewForAudience($audience);
        $binary = DomPdfFactory::loadView($view, $data)->output();

        return [
            'binary' => $binary,
            'filename' => $this->filename($event, $audience),
            'attachedFiles' => collect($data['attachedFiles'] ?? []),
            'package' => $package,
        ];
    }

    public function renderLiveHtml(Event $event, string $audience, ?EventPackageDocument $package = null): string
    {
        $package ??= $this->find($event, $audience);
        $data = $this->buildViewData($event, $audience, $package);

        return View::make($this->viewForAudience($audience), $data)->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Event $event, string $audience, ?EventPackageDocument $package = null): array
    {
        $this->assertAudience($audience);

        if ($audience === EventPackageDocument::AUDIENCE_DRIVER) {
            $data = $this->driverInfoBuilder->build($event);
            $data['logoDataUri'] = $this->dataFactory->resolveLogoDataUri();
            $data['audience'] = $audience;
            $data['audienceLabel'] = EventPackageDocument::$audienceLabels[$audience];
            $data['attachedFiles'] = collect();
        } else {
            $data = $this->dataFactory->make($event, $audience);
        }

        return $this->applyOverrides($data, $package);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyOverrides(array $payload, ?EventPackageDocument $package): array
    {
        $overrides = $package?->normalizedOverrides() ?? [
            'intro_html' => null,
            'extra_notes_html' => null,
            'hide_sections' => [],
            'custom_labels' => [],
        ];

        $payload['intro_html'] = $overrides['intro_html'];
        $payload['extra_notes_html'] = $overrides['extra_notes_html'];
        $payload['hide_sections'] = $overrides['hide_sections'];
        $payload['packageOverrides'] = $overrides;

        $customSubtitle = $overrides['custom_labels']['audience_subtitle'] ?? null;
        if (filled($customSubtitle)) {
            $payload['audienceLabel'] = $customSubtitle;
        }

        if (in_array('pilot_set_finance', $overrides['hide_sections'], true)) {
            $payload['pilotSetFinanceCards'] = [];
        }

        if (in_array('attachments_list', $overrides['hide_sections'], true)) {
            $payload['selectedSettlementDocuments'] = collect();
            $payload['attachedFiles'] = collect();
        }

        return $payload;
    }

    public function viewForAudience(string $audience): string
    {
        return match ($audience) {
            EventPackageDocument::AUDIENCE_PILOT,
            EventPackageDocument::AUDIENCE_HOTEL,
            EventPackageDocument::AUDIENCE_FOLDER => 'pdf.packages.'.$audience,
            EventPackageDocument::AUDIENCE_DRIVER => 'documents.driver-info',
            default => throw new InvalidArgumentException("Nieobsługiwany audience: {$audience}"),
        };
    }

    public function filename(Event $event, string $audience): string
    {
        $safeName = str($event->name ?: 'impreza')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->value();

        $label = match ($audience) {
            EventPackageDocument::AUDIENCE_PILOT => 'pilot',
            EventPackageDocument::AUDIENCE_HOTEL => 'hotel',
            EventPackageDocument::AUDIENCE_DRIVER => 'kierowca',
            EventPackageDocument::AUDIENCE_FOLDER => 'teczka',
            default => $audience,
        };

        return sprintf('%s-%s-%d.pdf', $safeName ?: 'impreza', $label, $event->id);
    }

    private function assertAudience(string $audience): void
    {
        if (! in_array($audience, EventPackageDocument::AUDIENCES, true)) {
            throw new InvalidArgumentException("Nieobsługiwany audience pakietu: {$audience}");
        }
    }
}
