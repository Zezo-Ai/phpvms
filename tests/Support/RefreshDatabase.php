<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase as BaseRefreshDatabase;
use Tests\TestCase;

trait RefreshDatabase
{
    use BaseRefreshDatabase;

    protected function migrateDatabases()
    {
        $this->artisan('migrate:fresh', array_merge(
            $this->migrateFreshUsing(),
            ['--database' => TestCase::TEST_DATABASE_CONNECTION, '--force' => true],
        ));
    }
}
