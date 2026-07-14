<?php

namespace Tests\Feature;

use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $marker = storage_path(Installer::INSTALLED_MARKER);
        if (File::exists($marker)) {
            File::delete($marker);
        }

        parent::tearDown();
    }

    public function test_is_installed_when_migrations_table_exists(): void
    {
        $this->assertTrue(Installer::isInstalled());
    }

    public function test_mark_installed_creates_marker_file(): void
    {
        $marker = storage_path(Installer::INSTALLED_MARKER);
        if (File::exists($marker)) {
            File::delete($marker);
        }

        Installer::markInstalled();

        $this->assertFileExists($marker);
        $this->assertTrue(Installer::isInstalled());
    }

    public function test_install_route_blocked_when_already_installed(): void
    {
        $this->assertTrue(Installer::isInstalled());

        $response = $this->get('/install');

        $response->assertRedirect();
    }
}
