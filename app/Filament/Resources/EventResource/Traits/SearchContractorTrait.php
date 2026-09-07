<?php

namespace App\Filament\Resources\EventResource\Traits;

use App\Models\Contractor;
use App\Support\PhoneValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Trait do wyszukiwania i mapowania danych kontrahenta.
 * Dzieli się odpowiedzialnością za znalezienie kontrahenta na podstawie
 * danych wejściowych (NIP, telefon, imię, adres, itp).
 */
trait SearchContractorTrait
{
    /**
     * Wyszukaj kontrahenta na podstawie kryteriów (NIP, nazwa, telefon, adres, itp.)
     */
    public static function searchContractor(array $criteria): ?Contractor
    {
        $criteria = array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $criteria);

        if (
            empty($criteria['nip'])
            && empty($criteria['name'])
            && empty($criteria['phone'])
            && empty($criteria['city'])
            && empty($criteria['street'])
        ) {
            return null;
        }

        $query = Contractor::query();

        // Wyszukaj po NIP
        if (! empty($criteria['nip'])) {
            if (Schema::hasColumn('contractors', 'nip')) {
                $query->where('nip', 'like', '%'.$criteria['nip'].'%');
            } else {
                $query->where('office_notes', 'like', '%'.$criteria['nip'].'%');
            }
        }

        // Wyszukaj po nazwie (dokładnie lub częściowo)
        if (! empty($criteria['name'])) {
            $query->where('name', 'like', '%'.$criteria['name'].'%');
        }

        // Wyszukaj po telefonie (kontrahent + kontakty), ignorując separatory
        if (! empty($criteria['phone'])) {
            $phone = $criteria['phone'];

            $query->where(function ($q) use ($phone) {
                $hasPhoneColumn = Schema::hasColumn('contractors', 'phone');

                if ($hasPhoneColumn) {
                    PhoneValidation::constrainDigitsLike($q, 'phone', $phone);
                }

                if (Contractor::hasContactPivotTable()) {
                    if ($hasPhoneColumn) {
                        $q->orWhereHas('contacts', function ($contactQuery) use ($phone) {
                            PhoneValidation::constrainDigitsLike($contactQuery, 'phone', $phone);
                        });
                    } else {
                        $q->whereHas('contacts', function ($contactQuery) use ($phone) {
                            PhoneValidation::constrainDigitsLike($contactQuery, 'phone', $phone);
                        });
                    }
                }
            });
        }

        // Wyszukaj po adresie
        if (! empty($criteria['city'])) {
            $query->where('city', 'like', '%'.$criteria['city'].'%');
        }

        if (! empty($criteria['street'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('street', 'like', '%'.$criteria['street'].'%')
                    ->orWhere('house_number', 'like', '%'.$criteria['street'].'%');
            });
        }

        return $query->first();
    }

    /**
     * Mapuj dane kontrahenta do formularza Event
     */
    public static function mapContractorToEventData(Contractor $contractor): array
    {
        $mainContact = Contractor::hasContactPivotTable()
            ? $contractor->contacts()->first()
            : null;

        return [
            'client_name' => $contractor->name,
            'client_email' => $mainContact?->email ?? $contractor->email,
            'client_phone' => $mainContact?->phone ?? $contractor->phone,
        ];
    }

    /**
     * Zwróć listę kontrahentów do wyboru w dropdown (dla Form::make()->relationship())
     * Wyllowaj na podstawie current search query
     */
    public static function getContractorOptions(string $searchQuery = ''): Collection
    {
        $query = Contractor::query();

        if (! empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('email', 'like', "%{$searchQuery}%")
                    ->orWhere('nip', 'like', "%{$searchQuery}%")
                    ->orWhere('street', 'like', "%{$searchQuery}%")
                    ->orWhere('city', 'like', "%{$searchQuery}%")
                    ->orWhere('postal_code', 'like', "%{$searchQuery}%");

                PhoneValidation::orWhereDigitsLike($q, 'phone', $searchQuery);
            });
        }

        return $query
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Contractor $c) => [
                $c->id => "{$c->name} (".($c->city ?? 'brak miasta').')',
            ]);
    }
}
