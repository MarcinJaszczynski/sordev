<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Events\AttachPilotEventVehiclePhotosAction;
use App\Models\Event;
use App\Models\EventVehicle;
use App\Models\Vehicle;
use App\Services\PilotAccessService;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PilotEventVehiclePhotos extends Component
{
    use WithFileUploads;

    public Event $event;

    /** @var TemporaryUploadedFile|array<int, TemporaryUploadedFile>|null */
    public $photos = null;

    public function mount(Event $event): void
    {
        abort_unless(Auth::user()?->can('viewPilotDetails', $event), 403);

        $this->event = $event->loadMissing(['eventVehicles.vehicle']);
    }

    public function getReadOnlyProperty(): bool
    {
        return app(PilotAccessService::class)->isPreviewReadOnly();
    }

    public function getAssignmentProperty(): ?EventVehicle
    {
        return $this->event->mainEventVehicle();
    }

    public function getVehicleProperty(): ?Vehicle
    {
        return $this->event->mainFleetVehicle();
    }

    /**
     * @return list<array{path: string, url: string}>
     */
    public function getPhotoItemsProperty(): array
    {
        if (! Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            return [];
        }

        return collect($this->assignment?->pilotPhotoPaths() ?? [])
            ->map(fn (string $path): array => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ])
            ->all();
    }

    public function updatedPhotos(): void
    {
        $this->uploadPhotos();
    }

    public function uploadPhotos(): void
    {
        if ($this->readOnly) {
            $this->reset('photos');

            return;
        }

        $rules = is_array($this->photos)
            ? [
                'photos' => ['required', 'array', 'min:1'],
                'photos.*' => ['image', 'max:8192'],
            ]
            : [
                'photos' => ['required', 'image', 'max:8192'],
            ];

        $this->validate($rules, [], [
            'photos' => 'zdjęcia',
            'photos.*' => 'zdjęcie',
        ]);

        $user = Auth::user();
        abort_unless($user !== null, 403);

        $files = $this->normalizeUploadedFiles($this->photos);
        if ($files === []) {
            $this->addError('photos', 'Wybierz co najmniej jedno zdjęcie.');

            return;
        }

        try {
            app(AttachPilotEventVehiclePhotosAction::class)(
                $this->event,
                $user,
                $files,
            );
        } catch (ValidationException $e) {
            $this->addError('photos', collect($e->errors())->flatten()->first() ?: $e->getMessage());
            $this->reset('photos');

            return;
        }

        $this->reset('photos');
        $this->event->unsetRelation('eventVehicles');
        $this->event->load(['eventVehicles.vehicle']);

        Notification::make()
            ->title('Dodano zdjęcia autokaru')
            ->success()
            ->send();
    }

    public function deletePhoto(string $path): void
    {
        if ($this->readOnly) {
            return;
        }

        $user = Auth::user();
        abort_unless($user !== null, 403);

        try {
            app(AttachPilotEventVehiclePhotosAction::class)->delete(
                $this->event,
                $user,
                $path,
            );
        } catch (ValidationException $e) {
            $this->addError('photos', collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        }

        $this->event->unsetRelation('eventVehicles');
        $this->event->load(['eventVehicles.vehicle']);

        Notification::make()
            ->title('Usunięto zdjęcie')
            ->success()
            ->send();
    }

    /**
     * @param  TemporaryUploadedFile|array<int, TemporaryUploadedFile>|mixed  $input
     * @return list<UploadedFile>
     */
    private function normalizeUploadedFiles(mixed $input): array
    {
        $items = is_array($input) ? $input : [$input];

        return collect($items)
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
            ->map(fn (TemporaryUploadedFile $file) => new UploadedFile(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                null,
                true,
            ))
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.pilot-event-vehicle-photos');
    }
}
