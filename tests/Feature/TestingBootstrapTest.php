<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class TestingBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_bootstrap_syncs_settings_after_migration(): void
    {
        $expectedPath = dirname(__DIR__, 2).'/storage/testing.sqlite';
        $actualPath = config('database.connections.'.self::TEST_DATABASE_CONNECTION.'.database');

        if (!empty($_SERVER['LARAVEL_PARALLEL_TESTING']) && !empty($_SERVER['TEST_TOKEN'])) {
            $expectedPath .= '_test_'.$_SERVER['TEST_TOKEN'];
        }

        $this->assertSame(self::TEST_DATABASE_CONNECTION, config('database.default'));
        $this->assertSame($expectedPath, $actualPath);
        $this->assertFileExists($actualPath);
        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertGreaterThan(0, DB::table('settings')->count());
    }
}
