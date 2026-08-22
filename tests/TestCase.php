<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** The ONLY database RefreshDatabase is ever allowed to migrate/wipe. */
    private const TEST_DATABASE = 'search_engine_test';

    /**
     * Point the test suite at a dedicated database BEFORE the application boots.
     *
     * RefreshDatabase runs migrate:fresh on the default connection. The project's
     * migrations use MySQL-specific DDL (ENUM / MODIFY COLUMN) so sqlite is not an
     * option; instead tests run against a separate MySQL database. This Docker env
     * sets DB_DATABASE=search_engine (the REAL data) in $_SERVER, and Laravel's
     * env() reads $_SERVER first — so we override it here (every source env() reads)
     * before parent::setUp() creates the app, and hard-abort if anything still
     * points at a non-test database. Queue/cache/session are forced to in-process
     * drivers so tests never touch Redis.
     */
    protected function setUp(): void
    {
        $overrides = [
            'DB_DATABASE'      => self::TEST_DATABASE,
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE'      => 'array',
            'SESSION_DRIVER'   => 'array',
        ];
        foreach ($overrides as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();

        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");
        if ($database !== self::TEST_DATABASE) {
            throw new \RuntimeException(
                "Refusing to run tests: database is '{$database}', not '" . self::TEST_DATABASE . "'. "
                . 'Tests would wipe a real database — aborting.'
            );
        }
    }
}
