<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Contractor;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'contractor_id' => null,
            'type' => VehicleType::Bus,
            'brand' => $this->faker->randomElement(['Mercedes', 'Setra', 'MAN', 'Volvo']),
            'model' => $this->faker->randomElement(['Tourismo', 'S 516', 'Lion\'s Coach', '9700']),
            'manufacture_year' => $this->faker->optional(0.7)->numberBetween(2012, (int) date('Y')),
            'registration_number' => strtoupper($this->faker->unique()->bothify('?? #####')),
            'capacity' => $this->faker->randomElement([19, 35, 49, 55]),
            'crew_seats' => 2,
            'equipment' => [Vehicle::EQUIPMENT_AC, Vehicle::EQUIPMENT_WIFI],
            'status' => VehicleStatus::Active,
            'is_ad_hoc' => false,
            'primary_image' => null,
            'gallery' => null,
            'attachments' => null,
            'notes' => null,
        ];
    }

    public function forContractor(Contractor $contractor): static
    {
        return $this->state(fn (): array => [
            'contractor_id' => $contractor->id,
            'is_ad_hoc' => false,
        ]);
    }

    public function adHoc(?Contractor $contractor = null): static
    {
        return $this->state(fn (): array => [
            'contractor_id' => $contractor?->id,
            'is_ad_hoc' => true,
        ]);
    }
}
