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
        $this->configureTestingDatabaseEnvironment();

        $app = parent::createApplication();

        $app['config']->set('database.default', self::TEST_DATABASE_CONNECTION);
        $app['config']->set('database.connections.sqlite.database', $this->testing_database_path());

        return $app;
    }

    protected function setUpRefreshDatabase(): void
    {
        app(SeederService::class)->syncAllSettings();
    }

    private function configureTestingDatabaseEnvironment(): void
    {
        $_ENV['DB_CONNECTION'] = self::TEST_DATABASE_CONNECTION;
        $_SERVER['DB_CONNECTION'] = self::TEST_DATABASE_CONNECTION;
        $_ENV['DB_DATABASE'] = $this->testing_database_path();
        $_SERVER['DB_DATABASE'] = $this->testing_database_path();

        $database = $this->testing_database_path();

        if (is_file($database)) {
            return;
        }

        if (!is_dir(dirname($database))) {
            mkdir(dirname($database), 0777, true);
        }

        touch($database);
    }

    private function testing_database_path(): string
    {
        return dirname(__DIR__).'/storage/testing.sqlite';
    }
}
