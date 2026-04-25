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
        $this->assertSame(self::TEST_DATABASE_CONNECTION, config('database.default'));
        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertGreaterThan(0, DB::table('settings')->count());
    }
}
