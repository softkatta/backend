<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for sqlite in-memory tests.');
        }
    }
}
