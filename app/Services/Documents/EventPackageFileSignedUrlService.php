<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventSettlementDocument;
use App\Services\EventPrintPdfDataFactory;
use App\Support\StoragePath;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Tymczasowe signed URL-e do plików z pakietów PDF (pilot / hotel / kierowca / teczka).
 * TTL: end_date imprezy + 5 dni (gdy minął — od teraz + 5 dni).
 */
final class EventPackageFileSignedUrlService
{
    public const AUDIENCES = ['pilot', 'hotel', 'driver', 'folder'];

    public const KIND_EVENT_DOCUMENT = 'event_document';

    public const KIND_SETTLEMENT_DOCUMENT = 'settlement_document';

    public const KIND_INSURANCE = 'insurance';

    public function expiresAt(Event $event): CarbonInterface
    {
        $base = $event->end_date?->copy()?->startOfDay() ?? now()->startOfDay();
        $expires = $base->copy()->addDays(5)->endOfDay();

        if ($expires->isPast()) {
            return now()->addDays(5);
        }

        return $expires;
    }

    /**
     * @param  array{
     *     kind: string,
     *     ref: string|int,
     *     file_index?: int,
     * }  $source
     */
    public function make(Event $event, string $audience, array $source): string
    {
        $audience = $this->assertAudience($audience);
        $kind = $this->assertKind((string) ($source['kind'] ?? ''));
        $ref = (string) ($source['ref'] ?? '');
        $fileIndex = max(0, (int) ($source['file_index'] ?? 0));

        if ($ref === '') {
            throw new InvalidArgumentException('Brak ref pliku pakietu.');
        }

        return URL::temporarySignedRoute(
            'shared.events.package-files.download',
            $this->expiresAt($event),
            [
                'event' => $event->getKey(),
                'audience' => $audience,
                'kind' => $kind,
                'ref' => $ref,
                'fileIndex' => $fileIndex,
            ],
        );
    }

    /**
     * @return array{absolute_path: string, download_name: string, mime: ?string}
     */
    public function resolveDownload(Event $event, string $audience, string $kind, string $ref, int $fileIndex = 0): array
    {
        $audience = $this->assertAudience($audience);
        $kind = $this->assertKind($kind);
        $flag = app(EventPrintPdfDataFactory::class)->attachmentFlagForAudience($audience);

        return match ($kind) {
            self::KIND_EVENT_DOCUMENT => $this->resolveEventDocument($event, $flag, $ref),
            self::KIND_SETTLEMENT_DOCUMENT => $this->resolveSettlementDocument($event, $flag, $ref, $fileIndex),
            self::KIND_INSURANCE => $this->resolveInsurance($event, $audience, $ref),
            default => abort(404),
        };
    }

    /**
     * @return array{absolute_path: string, download_name: string, mime: ?string}
     */
    private function resolveEventDocument(Event $event, string $flag, string $ref): array
    {
        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->whereKey((int) $ref)
            ->firstOrFail();

        abort_unless((bool) ($document->{$flag} ?? false), 403);
        abort_if(($document->approval_status ?? 'pending') === 'rejected', 403);
        abort_unless(filled($document->file_path), 404);

        $resolved = $this->absoluteFromRelative((string) $document->file_path);
        abort_unless($resolved !== null, 404);

        return [
            'absolute_path' => $resolved['absolute_path'],
            'download_name' => $document->original_filename ?: $resolved['base_name'],
            'mime' => $document->mime_type,
        ];
    }

    /**
     * @return array{absolute_path: string, download_name: string, mime: ?string}
     */
    private function resolveSettlementDocument(Event $event, string $flag, string $ref, int $fileIndex): array
    {
        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        abort_unless($settlement, 404);
        $settlementId = (int) $settlement->id;

        $document = EventSettlementDocument::query()
            ->where('settlement_id', $settlementId)
            ->whereKey((int) $ref)
            ->firstOrFail();

        abort_unless((bool) ($document->{$flag} ?? false), 403);
        abort_if(($document->approval_status ?? 'pending') === 'rejected', 403);

        $files = array_values(array_filter(
            $document->files ?? [],
            static fn ($path): bool => is_string($path) && $path !== '',
        ));

        abort_unless(isset($files[$fileIndex]), 404);

        $resolved = $this->absoluteFromRelative((string) $files[$fileIndex]);
        abort_unless($resolved !== null, 404);

        $label = $document->document_number ?: ('Dokument-'.$document->id);

        return [
            'absolute_path' => $resolved['absolute_path'],
            'download_name' => $label.'-'.$resolved['base_name'],
            'mime' => null,
        ];
    }

    /**
     * @return array{absolute_path: string, download_name: string, mime: ?string}
     */
    private function resolveInsurance(Event $event, string $audience, string $ref): array
    {
        abort_unless(in_array($audience, ['pilot', 'folder'], true), 403);

        $file = collect($event->insuranceFilesForPilot())
            ->first(fn (array $row): bool => (string) ($row['key'] ?? '') === $ref);

        abort_unless(is_array($file) && filled($file['path'] ?? null), 404);

        $resolved = $this->absoluteFromRelative((string) $file['path']);
        abort_unless($resolved !== null, 404);

        return [
            'absolute_path' => $resolved['absolute_path'],
            'download_name' => ((string) ($file['label'] ?? 'ubezpieczenie')).'-'.$resolved['base_name'],
            'mime' => null,
        ];
    }

    /**
     * @return array{absolute_path: string, base_name: string}|null
     */
    private function absoluteFromRelative(string $relativePath): ?array
    {
        $normalizedPath = StoragePath::normalize($relativePath);
        if (! $normalizedPath) {
            return null;
        }

        foreach (['public', config('filesystems.default')] as $diskName) {
            if (! $diskName) {
                continue;
            }

            $disk = Storage::disk((string) $diskName);
            if ($disk->exists($normalizedPath)) {
                return [
                    'absolute_path' => $disk->path($normalizedPath),
                    'base_name' => basename($normalizedPath),
                ];
            }
        }

        return null;
    }

    private function assertAudience(string $audience): string
    {
        abort_unless(in_array($audience, self::AUDIENCES, true), 404);

        return $audience;
    }

    private function assertKind(string $kind): string
    {
        abort_unless(in_array($kind, [
            self::KIND_EVENT_DOCUMENT,
            self::KIND_SETTLEMENT_DOCUMENT,
            self::KIND_INSURANCE,
        ], true), 404);

        return $kind;
    }
}
