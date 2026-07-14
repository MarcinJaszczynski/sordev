<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use App\Models\TfgFeedLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates a CSV "Wykaz umów" export: builds the file, persists it for audit
 * as a TfgFeedLog and returns the content ready to stream to the user.
 */
class TfgCsvExportService
{
    public function __construct(
        private readonly TfgWykazCsvExporter $exporter,
        private readonly TfgCsvValidator $validator,
    ) {}

    /**
     * @return array<int, string>
     */
    public function validate(Collection $contracts, string $operation): array
    {
        return $this->validator->validateCollection($contracts, $operation);
    }

    /**
     * @return array{log: TfgFeedLog, filename: string, content: string, contracts_count: int, rows_count: int, errors: array<int, string>}
     */
    public function generate(Collection $contracts, string $operation): array
    {
        $errors = $this->validate($contracts, $operation);

        if ($errors !== []) {
            throw new \App\Services\Tfg\Exceptions\TfgValidationException(
                'Eksport CSV zablokowany - popraw błędy walidacji.',
                $errors,
            );
        }

        $export = $this->exporter->export($contracts, $operation);

        $dir = (string) config('tfg.csv.storage_dir', 'tfg/csv');
        $path = $dir.'/'.now()->format('Ymd_His').'_'.strtolower($operation).'_'.uniqid().'.csv';
        Storage::disk('local')->put($path, $export['content']);

        $log = TfgFeedLog::create([
            'operation_type' => $operation,
            'contracts_count' => $export['contracts_count'],
            'sync_status' => 'CSV_EXPORTED',
            'payload_path' => $path,
            'payload_hash' => hash('sha256', $export['content']),
            'payload_version' => TfgContractPayloadBuilder::PAYLOAD_VERSION,
            'submitted_at' => now(),
        ]);

        foreach ($contracts as $contract) {
            $log->contracts()->syncWithoutDetaching([
                $contract->id => [
                    'operation' => $operation,
                    'correction_reason' => $contract->correction_reason,
                ],
            ]);
        }

        return [
            'log' => $log,
            'filename' => $export['filename'],
            'content' => $export['content'],
            'contracts_count' => $export['contracts_count'],
            'rows_count' => $export['rows_count'],
            'errors' => [],
        ];
    }

    /**
     * Contracts eligible for a given operation export.
     */
    public function eligibleQuery(string $operation): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contract::query()->with(['variants.locations', 'variants.transports', 'payments', 'refunds', 'event']);

        return match ($operation) {
            Contract::OP_ROZWIAZANIE => $query->whereNotNull('tfg_termination_date'),
            default => $query,
        };
    }

    public function csvHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
    }
}
