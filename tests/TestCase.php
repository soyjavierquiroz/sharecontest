<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // The suite uses an ephemeral SQLite connection. Reset Laravel's static
        // migration flag before traits are booted so each test process migrates it.
        RefreshDatabaseState::$migrated = false;

        parent::setUp();

        // Laravel's cached in-memory PDO can be initialized by an earlier
        // non-database test. Ensure feature tests that request RefreshDatabase
        // still get this application's migrations in the ephemeral test DB.
        if (isset($this->traitsUsedByTest[RefreshDatabase::class]) && !Schema::hasTable('contests')) {
            $this->artisan('migrate:fresh', ['--force' => true]);
        }
    }
}
