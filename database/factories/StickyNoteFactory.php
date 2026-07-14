<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\StickyNote;
use App\Models\User;
use App\Support\StickyNotes\StickyNoteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class StickyNoteFactory extends Factory
{
    protected $model = StickyNote::class;

    public function definition(): array
    {
        return [
            'notable_type' => Event::class,
            'notable_id' => Event::factory(),
            'body' => $this->faker->sentence(12),
            'category' => StickyNoteCategory::GENERAL,
            'created_by' => User::factory(),
        ];
    }
}
