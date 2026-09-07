<?php

namespace App\Services;

use App\Models\Contractor;
use App\Support\PhoneValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ContractorLookupService
{
    /**
     * @param  array<int, string>  $typeNames
     * @return array<int, string>
     */
    public function searchOptions(
        string $search = '',
        array $typeNames = [],
        bool $searchAll = false,
        ?int $includeId = null,
        int $limit = 50,
        ?array $restrictToIds = null,
    ): array {
        $query = Contractor::query()->orderBy('name');

        if ($restrictToIds !== null) {
            if ($restrictToIds === []) {
                return $includeId
                    ? $this->optionsForIds([$includeId], $includeId)
                    : [];
            }

            $query->whereIn('id', $restrictToIds);
        }

        if (! $searchAll && $typeNames !== []) {
            $query->withAnyTypeName($typeNames);
        }

        $search = trim($search);

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        $contractors = $query->limit($limit)->get();

        if ($includeId && ! $contractors->contains('id', $includeId)) {
            $included = Contractor::query()->find($includeId);

            if ($included) {
                $contractors->prepend($included);
            }
        }

        return $contractors
            ->unique('id')
            ->mapWithKeys(fn (Contractor $contractor): array => [
                (int) $contractor->id => $this->formatOptionLabel($contractor),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function optionsForIds(array $ids, ?int $includeId = null): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($includeId && ! in_array($includeId, $ids, true)) {
            array_unshift($ids, $includeId);
        }

        if ($ids === []) {
            return [];
        }

        return Contractor::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Contractor $contractor): array => [
                (int) $contractor->id => $this->formatOptionLabel($contractor),
            ])
            ->all();
    }

    public function formatOptionLabel(Contractor $contractor): string
    {
        $label = $contractor->displayLabel();
        $person = trim(implode(' ', array_filter([
            filled($contractor->firstname) ? trim((string) $contractor->firstname) : null,
            filled($contractor->surname) ? trim((string) $contractor->surname) : null,
        ])));
        if ($person !== '' && ! str_contains(mb_strtolower($label), mb_strtolower($person))) {
            $label = $person.' · '.$label;
        }

        $city = filled($contractor->city) ? trim((string) $contractor->city) : 'brak miasta';

        return $label.' ('.$city.')';
    }

    /**
     * Każdy token z frazy musi pasować do któregoś z pól kontrahenta (AND).
     * Dzięki temu „Michał Chruściel” trafia w name / firstname+surname,
     * a nie wymaga dokładnego LIKE na całej frazie w jednej kolumnie.
     *
     * Wyjątek: fraza wyglądająca jak telefon — jedno porównanie znormalizowane
     * (ignoruje spacje / separatory), bez dzielenia na tokeny.
     */
    protected function applySearchFilter(Builder $query, string $search): void
    {
        if (PhoneValidation::looksLikePhone($search)) {
            $query->where(function (Builder $phoneQuery) use ($search): void {
                PhoneValidation::constrainDigitsLike($phoneQuery, 'phone', $search);

                if (Contractor::hasContactPivotTable()) {
                    $phoneQuery->orWhereHas('contacts', function (Builder $contactQuery) use ($search): void {
                        PhoneValidation::constrainDigitsLike($contactQuery, 'phone', $search);
                    });
                }
            });

            return;
        }

        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return;
        }

        $hasPersonColumns = Schema::hasColumn('contractors', 'firstname')
            && Schema::hasColumn('contractors', 'surname');
        $hasNip = Schema::hasColumn('contractors', 'nip');

        foreach ($tokens as $token) {
            $like = '%'.$token.'%';

            $query->where(function (Builder $tokenQuery) use ($like, $token, $hasPersonColumns, $hasNip): void {
                $tokenQuery->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('street', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('postal_code', 'like', $like);

                PhoneValidation::orWhereDigitsLike($tokenQuery, 'phone', $token);

                if ($hasNip) {
                    $tokenQuery->orWhere('nip', 'like', $like);
                }

                if ($hasPersonColumns) {
                    $tokenQuery->orWhere('firstname', 'like', $like)
                        ->orWhere('surname', 'like', $like)
                        ->orWhereRaw(
                            "CONCAT(COALESCE(firstname, ''), ' ', COALESCE(surname, '')) LIKE ?",
                            [$like]
                        );
                }

                $tokenQuery->orWhereHas('contacts', function (Builder $contactQuery) use ($like, $token): void {
                    $contactQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);

                    PhoneValidation::orWhereDigitsLike($contactQuery, 'phone', $token);
                });
            });
        }
    }
}
