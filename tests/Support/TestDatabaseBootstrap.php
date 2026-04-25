<?php

declare(strict_types=1);

namespace Tests\Support;

final class TestDatabaseBootstrap
{
    public const string TEST_DATABASE_CONNECTION = 'testing';

    public const string DEBUG_DATABASE_FLAG = 'PHPVMS_TEST_DEBUG_DATABASE';

    public static function bootstrap(): void
    {
        $debug = self::debugEnabled();
        $database = self::databasePath($debug);

        self::configureEnvironment($database, $debug);
        self::prepareDatabaseFile($database, $debug);
    }

    public static function databasePath(?bool $debug = null): string
    {
        $debug ??= self::debugEnabled();

        return dirname(__DIR__, 2).'/storage/'.($debug ? 'testing-debug.sqlite' : 'testing.sqlite');
    }

    public static function debugEnabled(): bool
    {
        $value = $_ENV[self::DEBUG_DATABASE_FLAG] ?? $_SERVER[self::DEBUG_DATABASE_FLAG] ?? getenv(self::DEBUG_DATABASE_FLAG) ?: false;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function configureEnvironment(string $database, bool $debug): void
    {
        self::setEnv('APP_ENV', 'testing');
        self::setEnv('DB_CONNECTION', self::TEST_DATABASE_CONNECTION);
        self::setEnv('DB_DATABASE', $database);
        self::setEnv('LARAVEL_PARALLEL_TESTING_RECREATE_DATABASES', '1');

        if ($debug) {
            self::unsetEnv('LARAVEL_PARALLEL_TESTING_DROP_DATABASES');
        } else {
            self::setEnv('LARAVEL_PARALLEL_TESTING_DROP_DATABASES', '1');
        }
    }

    public static function prepareDatabaseFile(string $database, bool $debug): void
    {
        if (!is_dir(dirname($database))) {
            mkdir(dirname($database), 0777, true);
        }

        if (is_file($database)) {
            unlink($database);
        }

        touch($database);

        $workerDatabase = self::workerDatabasePath($database);
        if ($workerDatabase !== null && is_file($workerDatabase)) {
            unlink($workerDatabase);
        }

        if (!$debug) {
            register_shutdown_function(static function () use ($database, $workerDatabase): void {
                if (is_file($database)) {
                    unlink($database);
                }

                if ($workerDatabase !== null && is_file($workerDatabase)) {
                    unlink($workerDatabase);
                }
            });
        }
    }

    public static function workerDatabasePath(string $database): ?string
    {
        $token = $_ENV['TEST_TOKEN'] ?? $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN') ?: null;
        if ($token === null || $token === '') {
            return null;
        }

        return $database.'_test_'.$token;
    }

    private static function setEnv(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private static function unsetEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
