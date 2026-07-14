<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreApplicationTest extends TestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePath = database_path('testing_backup_'.uniqid().'.sqlite');
        touch($this->sqlitePath);
        config(['database.connections.sqlite.database' => $this->sqlitePath]);
        $this->artisan('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if (isset($this->sqlitePath) && file_exists($this->sqlitePath)) {
            @unlink($this->sqlitePath);
        }

        parent::tearDown();
    }

    public function test_backup_and_restore_sqlite_and_storage_roundtrip(): void
    {
        $markerPath = storage_path('app/public/backup_test_marker.txt');
        File::ensureDirectoryExists(dirname($markerPath));
        File::put($markerPath, 'before-backup');

        $exitCode = Artisan::call('app:backup', [
            '--components' => 'db,storage',
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        preg_match('/BACKUP_PATH=(.+)/', $output, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $zipPath = trim($matches[1]);
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $zip->close();

        File::put($markerPath, 'after-backup-should-revert');

        $restoreCode = Artisan::call('app:restore', [
            'path' => $zipPath,
            '--components' => 'db,storage',
            '--force' => true,
        ]);

        $this->assertSame(0, $restoreCode);
        $this->assertSame('before-backup', File::get($markerPath));

        File::delete($markerPath);
        File::delete($zipPath);
    }
}
