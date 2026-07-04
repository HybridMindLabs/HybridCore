<?php

namespace Tests\Unit;

use App\Services\Extensions\ExtensionManifestValidator;
use Tests\TestCase;

class ExtensionManifestValidatorTest extends TestCase
{
    private ExtensionManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ExtensionManifestValidator;
    }

    public function test_valid_manifest_passes(): void
    {
        $result = $this->validator->validate([
            'id' => 'hybridcore/demo',
            'name' => 'Demo',
            'version' => '1.0.0',
            'description' => 'A demo.',
            'author' => 'HybridCore',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_missing_id_fails(): void
    {
        $result = $this->validator->validate(['name' => 'X', 'version' => '1.0.0']);

        $this->assertFalse($result['valid']);
        $this->assertContains('missing required field: id (or slug)', $result['errors']);
    }

    public function test_legacy_slug_is_accepted_as_id(): void
    {
        $result = $this->validator->validate([
            'slug' => 'hybridcore-example',
            'name' => 'Example',
            'version' => '1.0.0',
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_version_fails(): void
    {
        $result = $this->validator->validate([
            'id' => 'a/b', 'name' => 'X', 'version' => 'not-a-version',
        ]);

        $this->assertFalse($result['valid']);
    }

    public function test_missing_description_and_author_only_warn(): void
    {
        $result = $this->validator->validate([
            'id' => 'a/b', 'name' => 'X', 'version' => '1.0',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['warnings']);
    }

    public function test_wrong_types_for_optional_fields_fail(): void
    {
        $result = $this->validator->validate([
            'id' => 'a/b', 'name' => 'X', 'version' => '1.0',
            'permissions' => 'not-an-array',
            'provider' => ['not-a-string'],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
    }
}
