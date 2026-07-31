<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give each parallel worker its own storage directory.
     *
     * Many suites mark the app installed by writing storage/installed.lock in
     * setUp and removing it in tearDown. Serially that is fine; under
     * `--parallel` the workers share one directory, so one process tearing the
     * lock down un-installs the app for the others mid-test and their requests
     * redirect to the installer.
     *
     * Repointing path.storage is enough — storage_path() then resolves per
     * worker, isolating every existing test without touching any of them.
     * Sessions and cache already use array drivers under phpunit.xml, so
     * nothing else in storage is contended.
     */
    /**
     * Set to false by suites that assert against the real storage paths
     * themselves — the installer's writability checks, for instance.
     */
    protected bool $isolatesParallelStorage = true;

    public function createApplication()
    {
        $app = parent::createApplication();

        $token = ParallelTesting::token();

        if ($this->isolatesParallelStorage && $token !== false && $token !== null) {
            $app->useStoragePath(static::isolatedStoragePath((string) $token));
        }

        return $app;
    }

    /** Create (once per worker) and return that worker's storage root. */
    private static function isolatedStoragePath(string $token): string
    {
        $path = dirname(__DIR__)."/storage/parallel/{$token}";

        if (! is_dir($path.'/app/public')) {
            @mkdir($path.'/app/public', 0777, true);
            @mkdir($path.'/framework/cache/data', 0777, true);
            @mkdir($path.'/framework/sessions', 0777, true);
            @mkdir($path.'/framework/views', 0777, true);
            @mkdir($path.'/logs', 0777, true);
        }

        return $path;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
