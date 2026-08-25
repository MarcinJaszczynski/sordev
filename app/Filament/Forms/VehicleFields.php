<?php

namespace App\Filament\Forms;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Contractor;
use App\Models\Vehicle;
use Filament\Forms;
use Illuminate\Support\Facades\Storage;

/**
 * Wspólny schemat pól pojazdu (resource + relation manager + createOption + modal z imprezy).
 */
final class VehicleFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(bool $includeContractor = true, bool $includeMedia = true): array
    {
        $fields = [];

        if ($includeContractor) {
            $fields[] = Forms\Components\Select::make('contractor_id')
                ->label('Przewoźnik')
                ->options(fn (): array => Contractor::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->nullable()
                ->helperText('Puste = pojazd ad-hoc / lokalny podwykonawca bez karty firmy.');
        }

        $fields = [
            ...$fields,
            Forms\Components\Select::make('type')
                ->label('Typ')
                ->options(VehicleType::options())
                ->default(VehicleType::Bus->value)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('brand')
                ->label('Marka')
                ->maxLength(120)
                ->nullable(),
            Forms\Components\TextInput::make('model')
                ->label('Model')
                ->maxLength(120)
                ->nullable(),
            Forms\Components\TextInput::make('registration_number')
                ->label('Nr rejestracyjny')
                ->required()
                ->maxLength(32)
                ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null
                    ? mb_strtoupper(trim($state))
                    : null),
            Forms\Components\TextInput::make('capacity')
                ->label('Miejsca pasażerskie')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->nullable()
                ->helperText('Miejsca dla uczestników wycieczki (bez kierowcy/pilota).'),
            Forms\Components\TextInput::make('crew_seats')
                ->label('Miejsca załogi')
                ->numeric()
                ->minValue(0)
                ->maxValue(10)
                ->default(2)
                ->nullable()
                ->helperText('Kierowca + pilot (np. 2). Łącznie widać jako 49+2.'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(VehicleStatus::options())
                ->default(VehicleStatus::Active->value)
                ->required()
                ->native(false),
            Forms\Components\Toggle::make('is_ad_hoc')
                ->label('Pojazd ad-hoc')
                ->helperText('Lokalny / jednorazowy autokar (np. podstawiony na wyjeździe).')
                ->default(false)
                ->inline(false),
            Forms\Components\CheckboxList::make('equipment')
                ->label('Wyposażenie')
                ->options(Vehicle::equipmentOptions())
                ->columns(2)
                ->columnSpanFull(),
        ];

        if ($includeMedia) {
            $fields = [...$fields, ...self::mediaAndNotesFields()];
        }

        return $fields;
    }

    /**
     * Zdjęcia, pliki dokumentów i uwagi — wspólne dla resource / RM / modalu z Transportu.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function mediaAndNotesFields(): array
    {
        return [
            Forms\Components\FileUpload::make('primary_image')
                ->label('Zdjęcie główne')
                ->image()
                ->imageEditor()
                ->disk('public')
                ->directory('vehicles/primary')
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                ->getUploadedFileUsing(self::uploadedFilePreview(...))
                ->nullable()
                ->helperText('Okładka pojazdu w liście i na imprezie.'),
            Forms\Components\FileUpload::make('gallery')
                ->label('Galeria zdjęć')
                ->image()
                ->multiple()
                ->reorderable()
                ->disk('public')
                ->directory('vehicles/gallery')
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                ->getUploadedFileUsing(self::uploadedFilePreview(...))
                ->nullable()
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('attachments')
                ->label('Pliki / dokumenty')
                ->multiple()
                ->reorderable()
                ->disk('public')
                ->directory('vehicles/attachments')
                ->visibility('public')
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->getUploadedFileUsing(self::uploadedFilePreview(...))
                ->nullable()
                ->helperText('Np. przegląd techniczny, ubezpieczenie OC, umowa, skan tabliczki.')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')
                ->label('Uwagi do pojazdu')
                ->rows(4)
                ->nullable()
                ->helperText('Wyposażenie niestandardowe, ograniczenia, uwagi dla pilota / kierowcy.')
                ->columnSpanFull(),
        ];
    }

    /**
     * Formularz createOption / szybkie dodanie z imprezy (z mediami).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function createOptionSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Typ')
                ->options(VehicleType::options())
                ->default(VehicleType::Bus->value)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('brand')
                ->label('Marka')
                ->maxLength(120),
            Forms\Components\TextInput::make('model')
                ->label('Model')
                ->maxLength(120),
            Forms\Components\TextInput::make('registration_number')
                ->label('Nr rejestracyjny')
                ->required()
                ->maxLength(32),
            Forms\Components\TextInput::make('capacity')
                ->label('Miejsca pasażerskie')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->helperText('Bez kierowcy/pilota.'),
            Forms\Components\TextInput::make('crew_seats')
                ->label('Miejsca załogi')
                ->numeric()
                ->minValue(0)
                ->maxValue(10)
                ->default(2)
                ->helperText('Np. 2 = kierowca + pilot → łącznie 49+2.'),
            Forms\Components\CheckboxList::make('equipment')
                ->label('Wyposażenie')
                ->options(Vehicle::equipmentOptions())
                ->columns(2)
                ->columnSpanFull(),
            ...self::mediaAndNotesFields(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mediaPayloadFromForm(array $data): array
    {
        return [
            'primary_image' => $data['primary_image'] ?? null,
            'gallery' => array_values(array_filter((array) ($data['gallery'] ?? []))),
            'attachments' => array_values(array_filter((array) ($data['attachments'] ?? []))),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @return array{name: string, size: int, type: ?string, url: string}|null
     */
    private static function uploadedFilePreview(mixed $file, mixed $storedFileNames = null): ?array
    {
        if (blank($file)) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $size = $disk->exists($file) ? $disk->size($file) : 0;
            $type = $disk->exists($file) ? $disk->mimeType($file) : null;
        } catch (\Throwable) {
            $size = 0;
            $type = null;
        }

        return [
            'name' => basename((string) $file),
            'size' => $size,
            'type' => $type,
            'url' => '/storage/'.ltrim((string) $file, '/'),
        ];
    }
}
