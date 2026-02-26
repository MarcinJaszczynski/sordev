<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventTemplateQty;

class EventTemplateQtySeeder extends Seeder
{
    public function run(): void
    {
        EventTemplateQty::create(['qty' => 10]);
        EventTemplateQty::create(['qty' => 20]);
        EventTemplateQty::create(['qty' => 30]);
        EventTemplateQty::create(['qty' => 40]);
        EventTemplateQty::create(['qty' => 50]);
        EventTemplateQty::create(['qty' => 100]);
    }
}
