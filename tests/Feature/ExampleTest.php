<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_installer_when_not_installed(): void
    {
        @unlink(storage_path('installed.lock'));

        $this->get('/')->assertRedirectToRoute('installer.welcome');
    }
}
