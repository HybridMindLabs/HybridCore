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
