<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Installer\SeederService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const string TEST_DATABASE_CONNECTION = 'testing';

    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', self::TEST_DATABASE_CONNECTION);
        $app['config']->set('database.connections.'.self::TEST_DATABASE_CONNECTION.'.database', getenv('DB_DATABASE') ?: config('database.connections.'.self::TEST_DATABASE_CONNECTION.'.database'));

        return $app;
    }

    protected function setUpRefreshDatabase(): void
    {
        app(SeederService::class)->syncAllSettings();
    }
}
