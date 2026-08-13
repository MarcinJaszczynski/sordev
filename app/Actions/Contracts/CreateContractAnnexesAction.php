<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Services\ContractTfgSetupService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tworzy aneksy do jednej lub wielu umów (hurtowo z listy imprezy).
 *
 * @phpstan-type Result array{created: list<Contract>, skipped: int}
 */
final class CreateContractAnnexesAction
{
    public function __construct(
        private ContractTfgSetupService $tfg,
    ) {}

    /**
     * @param  iterable<int, Contract>  $parents
     * @param  array<string, mixed>  $data
     * @return Result
     */
    public function __invoke(iterable $parents, array $data): array
    {
        $created = [];
        $skipped = 0;

        return DB::transaction(function () use ($parents, $data, &$created, &$skipped): array {
            foreach ($parents as $parent) {
                if (! $parent instanceof Contract) {
                    $skipped++;

                    continue;
                }

                if ($parent->isAnnex() || $parent->status === 'template' || $parent->status === 'cancelled') {
                    $skipped++;

                    continue;
                }

                $payload = $data;
                // Kwota: jeśli nie podano — zachowaj z umowy rodzica (per umowa).
                if (! array_key_exists('amount_due', $payload) && ! array_key_exists('total_price', $payload)) {
                    $payload['amount_due'] = (float) $parent->amount_due;
                }
                if (! array_key_exists('participant_count', $payload)) {
                    $payload['participant_count'] = (int) ($parent->participant_count ?? 1);
                }

                $created[] = $this->tfg->createAnnex($parent, $payload);
            }

            if ($created === []) {
                throw new InvalidArgumentException(
                    'Nie utworzono żadnego aneksu. Wybierz podpisane/wysłane umowy (nie szablony i nie istniejące aneksy).'
                );
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }
}
