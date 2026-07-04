<?php

namespace Hybridcore\Demo\Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_extension_translations_load(): void
    {
        app('translator')->addNamespace('demo', base_path('extensions/hybridcore/demo/resources/lang'));

        $this->assertNotSame('demo::messages.welcome', trans('demo::messages.welcome'));
    }
}
