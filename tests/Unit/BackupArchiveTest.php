<?php

namespace Tests\Unit;

use App\Support\BackupArchive;
use PHPUnit\Framework\TestCase;

class BackupArchiveTest extends TestCase
{
    public function test_parse_components_filters_invalid(): void
    {
        $components = BackupArchive::parseComponents('db,storage,code,invalid');

        $this->assertSame(['db', 'storage', 'code'], $components);
    }

    public function test_should_skip_storage_backups_and_cache(): void
    {
        $this->assertTrue(BackupArchive::shouldSkipStoragePath('backups/backup_test.zip'));
        $this->assertTrue(BackupArchive::shouldSkipStoragePath('framework/cache/data/foo'));
        $this->assertFalse(BackupArchive::shouldSkipStoragePath('app/public/test.jpg'));
    }

    public function test_should_skip_code_vendor_and_env(): void
    {
        $this->assertTrue(BackupArchive::shouldSkipCodePath('vendor/autoload.php'));
        $this->assertTrue(BackupArchive::shouldSkipCodePath('.env'));
        $this->assertFalse(BackupArchive::shouldSkipCodePath('app/Models/User.php'));
    }

    public function test_full_application_components(): void
    {
        $this->assertSame(
            ['db', 'storage', 'code'],
            BackupArchive::FULL_APPLICATION_COMPONENTS,
        );
    }
}
