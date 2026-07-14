<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneBackupArchivesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_keeps_newest_backups(): void
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (glob($dir.'/backup_*.zip') ?: [] as $old) {
            @unlink($old);
        }

        $files = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = $dir.'/backup_prune_test_'.$i.'.zip';
            file_put_contents($path, 'test');
            touch($path, time() - (3 - $i) * 3600);
            $files[] = $path;
        }

        $this->artisan('app:backup-prune', ['--keep' => 2])
            ->assertExitCode(0);

        $remaining = glob($dir.'/backup_prune_test_*.zip') ?: [];
        $this->assertCount(2, $remaining);

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
