<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrateCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_check_succeeds_when_up_to_date(): void
    {
        $code = Artisan::call('app:migrate-check', ['--fail' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Wszystkie migracje', Artisan::output());
    }
}
