<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $dbConnection = (string) (
            $_ENV['DB_CONNECTION']
            ?? $_SERVER['DB_CONNECTION']
            ?? getenv('DB_CONNECTION')
            ?? 'sqlite'
        );

        if ($dbConnection === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for sqlite in-memory tests.');
        }

        parent::setUp();
    }
}
