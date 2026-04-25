<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Tests\Support\TestDatabaseBootstrap;
use Tests\TestCase;

class TestDatabaseBootstrapTest extends TestCase
{
    public function test_database_path_uses_default_test_file(): void
    {
        unset($_ENV[TestDatabaseBootstrap::DEBUG_DATABASE_FLAG], $_SERVER[TestDatabaseBootstrap::DEBUG_DATABASE_FLAG]);

        $this->assertSame(dirname(__DIR__, 3).'/storage/testing.sqlite', TestDatabaseBootstrap::databasePath(false));
    }

    public function test_database_path_uses_debug_test_file_when_enabled(): void
    {
        $_ENV[TestDatabaseBootstrap::DEBUG_DATABASE_FLAG] = '1';
        $_SERVER[TestDatabaseBootstrap::DEBUG_DATABASE_FLAG] = '1';

        $this->assertSame(dirname(__DIR__, 3).'/storage/testing-debug.sqlite', TestDatabaseBootstrap::databasePath());
    }

    public function test_prepare_database_file_replaces_a_stale_file(): void
    {
        $database = dirname(__DIR__, 3).'/storage/testing-bootstrap-unit.sqlite';
        file_put_contents($database, 'not-a-valid-sqlite-database');

        TestDatabaseBootstrap::prepareDatabaseFile($database, true);

        $this->assertFileExists($database);
        $this->assertSame(0, filesize($database));

        unlink($database);
    }
}
