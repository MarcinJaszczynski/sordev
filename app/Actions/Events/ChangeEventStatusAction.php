<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Data\ChangeEventStatusData;
use App\Events\EventStatusChanged;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Jedyny punkt mutacji statusu imprezy z UI / bulk / SelectColumn.
 */
final class ChangeEventStatusAction
{
    public function __invoke(ChangeEventStatusData $data): Event
    {
        $status = $data->status;
        $options = Event::getStatusOptions();

        if (! array_key_exists($status, $options)) {
            throw new InvalidArgumentException("Nieznany status imprezy: {$status}");
        }

        /** @var array{0: Event, 1: string|null} $result */
        $result = DB::transaction(function () use ($data, $status): array {
            $event = $data->event->fresh() ?? $data->event;
            $previous = (string) $event->status;

            if ($previous === $status) {
                return [$event, null];
            }

            $event->changeStatus($status, $data->reason);

            return [$event->fresh() ?? $event, $previous];
        });

        [$fresh, $previous] = $result;

        // Powiadomienia / automatyki poza transakcją — błąd maila/SMS nie cofa statusu.
        if ($previous !== null) {
            EventStatusChanged::dispatch($fresh, $previous, $status);
        }

        return $fresh;
    }
}
