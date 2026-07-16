<?php

namespace Tests\Feature\Installer;

use App\Services\InstallerService;
use Tests\TestCase;

/**
 * The requirements step exists to catch a broken environment before it turns
 * into a 500 the person installing cannot read — with no log to consult,
 * because the log directory is usually part of what is broken.
 */
class RequirementsWritableTest extends TestCase
{
    private function checkFor(string $label): ?array
    {
        foreach (app(InstallerService::class)->checkRequirements() as $check) {
            if (str_contains($check['label'], $label)) {
                return $check;
            }
        }

        return null;
    }

    public function test_it_checks_the_directories_the_app_actually_writes_to(): void
    {
        // storage/ being writable says nothing about these; the cache driver
        // and the session driver write here, not to the parent.
        foreach ([
            'storage/logs',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'bootstrap/cache',
        ] as $dir) {
            $this->assertNotNull(
                $this->checkFor($dir),
                "The requirements step should check [$dir] for writability.",
            );
        }
    }

    public function test_writable_directories_pass(): void
    {
        $check = $this->checkFor('storage/framework/cache/data');

        $this->assertTrue($check['passed']);
        $this->assertSame('Writable', $check['value']);
    }

    public function test_an_unwritable_directory_is_reported_and_is_critical(): void
    {
        $dir = storage_path('framework/cache/data');
        $original = fileperms($dir);

        chmod($dir, 0555); // readable, not writable

        try {
            $check = $this->checkFor('storage/framework/cache/data');

            $this->assertFalse($check['passed'], 'An unwritable cache directory must fail the check.');
            $this->assertTrue($check['critical'], 'It must block the install, not warn.');
            $this->assertStringContainsString('Not writable', $check['value']);
        } finally {
            chmod($dir, $original);
        }
    }

    public function test_an_unwritable_env_file_fails_the_check(): void
    {
        $env = base_path('.env');

        if (! file_exists($env)) {
            $this->markTestSkipped('No .env in this environment.');
        }

        $original = fileperms($env);
        chmod($env, 0444); // readable, not writable

        try {
            $check = $this->checkFor('.env');

            // The installer writes the database credentials here at the final
            // step; "the file exists" was previously enough to pass, so the
            // install died at the very end instead of failing here.
            $this->assertFalse($check['passed'], 'A read-only .env must fail the check.');
            $this->assertStringContainsString('not writable', strtolower($check['value']));
            $this->assertNotNull($check['fix'], 'A failed check must come with a fix.');
        } finally {
            chmod($env, $original);
        }
    }

    public function test_every_failed_check_carries_a_fix(): void
    {
        $dir = storage_path('framework/sessions');
        $original = fileperms($dir);

        chmod($dir, 0555);

        try {
            foreach (app(InstallerService::class)->checkRequirements() as $check) {
                if (! $check['passed']) {
                    $this->assertNotEmpty(
                        $check['fix'],
                        "Check [{$check['label']}] failed without telling the user how to fix it.",
                    );
                }
            }
        } finally {
            chmod($dir, $original);
        }
    }

    public function test_requirements_do_not_pass_overall_when_a_directory_is_unwritable(): void
    {
        $dir = storage_path('framework/views');
        $original = fileperms($dir);

        chmod($dir, 0555);

        try {
            $service = app(InstallerService::class);

            $this->assertFalse(
                $service->allRequirementsPassed($service->checkRequirements()),
                'The installer must not offer to continue past an unwritable directory.',
            );
        } finally {
            chmod($dir, $original);
        }
    }
}
