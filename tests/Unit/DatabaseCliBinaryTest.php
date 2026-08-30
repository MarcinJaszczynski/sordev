<?php

namespace Tests\Unit;

use App\Support\DatabaseCliBinary;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseCliBinaryTest extends TestCase
{
    #[Test]
    public function test_candidate_paths_include_homebrew_and_system_locations(): void
    {
        $paths = DatabaseCliBinary::candidatePaths('mysqldump');

        $this->assertContains('/usr/bin/mysqldump', $paths);
        $this->assertContains('/opt/homebrew/opt/mysql-client/bin/mysqldump', $paths);
        $this->assertContains('/usr/local/mysql/bin/mysqldump', $paths);
    }

    #[Test]
    public function test_find_resolves_mysqldump_when_dbngin_or_path_provides_it(): void
    {
        $dbngin = glob('/Users/Shared/DBngin/mysql/*/bin/mysqldump') ?: [];
        $which = trim((string) shell_exec('which mysqldump 2>/dev/null'));

        if ($dbngin === [] && ($which === '' || ! is_executable($which))) {
            $this->markTestSkipped('Brak mysqldump w PATH ani DBngin — pomijam na tym środowisku.');
        }

        $found = DatabaseCliBinary::find('mysqldump');

        $this->assertNotNull($found);
        $this->assertTrue(is_executable($found));
    }
}
