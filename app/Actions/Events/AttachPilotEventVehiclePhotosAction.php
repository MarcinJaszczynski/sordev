<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventVehicle;
use App\Models\User;
use App\Services\PilotAccessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Zdjęcia autokaru z portalu pilota — per przypisanie imprezy (nie galeria floty).
 */
final class AttachPilotEventVehiclePhotosAction
{
    public const MAX_PHOTOS = 12;

    public function __construct(
        private readonly PilotAccessService $pilotAccess,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function __invoke(Event $event, User $user, array $files): EventVehicle
    {
        $this->pilotAccess->assertPilotMutationsAllowed();

        if (! Schema::hasTable('event_vehicles') || ! Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            throw ValidationException::withMessages([
                'photos' => 'Funkcja zdjęć autokaru nie jest jeszcze dostępna.',
            ]);
        }

        if (! $user->can('viewPilotDetails', $event)) {
            throw ValidationException::withMessages([
                'photos' => 'Brak uprawnień do dodawania zdjęć autokaru.',
            ]);
        }

        $assignment = $event->mainEventVehicle();
        if (! $assignment) {
            throw ValidationException::withMessages([
                'photos' => 'Brak przypisanego autokaru na tej imprezie — biuro musi najpierw wybrać pojazd floty.',
            ]);
        }

        $files = array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));

        if ($files === []) {
            throw ValidationException::withMessages([
                'photos' => 'Wybierz co najmniej jedno zdjęcie.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $event, $files): EventVehicle {
            $assignment = EventVehicle::query()->lockForUpdate()->findOrFail($assignment->id);
            $existing = $assignment->pilotPhotoPaths();
            $remaining = self::MAX_PHOTOS - count($existing);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'photos' => 'Limit '.self::MAX_PHOTOS.' zdjęć został wyczerpany. Usuń stare, aby dodać nowe.',
                ]);
            }

            $toStore = array_slice($files, 0, $remaining);
            $directory = 'events/'.$event->id.'/pilot-vehicle-photos';

            foreach ($toStore as $file) {
                $path = $file->store($directory, 'public');
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $existing[] = $path;
            }

            $assignment->forceFill([
                'pilot_photos' => array_values(array_unique($existing)),
            ])->save();

            return $assignment->fresh(['vehicle']) ?? $assignment;
        });
    }

    public function delete(Event $event, User $user, string $path): EventVehicle
    {
        $this->pilotAccess->assertPilotMutationsAllowed();

        if (! $user->can('viewPilotDetails', $event)) {
            throw ValidationException::withMessages([
                'photos' => 'Brak uprawnień do usuwania zdjęć autokaru.',
            ]);
        }

        $assignment = $event->mainEventVehicle();
        if (! $assignment) {
            throw ValidationException::withMessages([
                'photos' => 'Brak przypisanego autokaru.',
            ]);
        }

        $path = ltrim($path, '/');
        if (! in_array($path, $assignment->pilotPhotoPaths(), true)) {
            throw ValidationException::withMessages([
                'photos' => 'Nie znaleziono tego zdjęcia.',
            ]);
        }

        $assignment->removePilotPhoto($path);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return $assignment->fresh(['vehicle']) ?? $assignment;
    }
}
