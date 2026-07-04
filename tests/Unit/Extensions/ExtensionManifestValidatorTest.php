<?php

namespace Tests\Unit\Extensions;

use App\Services\Extensions\ExtensionManifestValidator;
use PHPUnit\Framework\TestCase;

class ExtensionManifestValidatorTest extends TestCase
{
    private ExtensionManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ExtensionManifestValidator;
    }

    private function valid(): array
    {
        return [
            'id' => 'hybridcore/announcements',
            'name' => 'Announcements',
            'version' => '1.0.0',
            'description' => 'Test extension',
            'author' => 'HybridCore',
        ];
    }

    public function test_valid_manifest_passes(): void
    {
        $result = $this->validator->validate($this->valid());

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_missing_id_fails(): void
    {
        $manifest = $this->valid();
        unset($manifest['id']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_legacy_slug_field_accepted(): void
    {
        $manifest = $this->valid();
        unset($manifest['id']);
        $manifest['slug'] = 'hybridcore/legacy';

        $result = $this->validator->validate($manifest);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_id_format_fails(): void
    {
        $manifest = array_merge($this->valid(), ['id' => 'Invalid Extension ID!']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('invalid id format', $result['errors'][0]);
    }

    public function test_missing_name_fails(): void
    {
        $manifest = $this->valid();
        unset($manifest['name']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result['valid']);
    }

    public function test_missing_version_fails(): void
    {
        $manifest = $this->valid();
        unset($manifest['version']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result['valid']);
    }

    public function test_invalid_version_format_fails(): void
    {
        $manifest = array_merge($this->valid(), ['version' => 'not-semver']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result['valid']);
    }

    public function test_valid_semver_variants_pass(): void
    {
        foreach (['1.0.0', '2.3.1', '1.0.0-beta.1', '0.1.0+build.42'] as $version) {
            $manifest = array_merge($this->valid(), ['version' => $version]);
            $result = $this->validator->validate($manifest);
            $this->assertTrue($result['valid'], "Version {$version} should be valid");
        }
    }

    public function test_missing_description_produces_warning_not_error(): void
    {
        $manifest = $this->valid();
        unset($manifest['description']);

        $result = $this->validator->validate($manifest);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_missing_author_produces_warning(): void
    {
        $manifest = $this->valid();
        unset($manifest['author']);

        $result = $this->validator->validate($manifest);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_valid_id_formats_pass(): void
    {
        foreach (['vendor/name', 'my-vendor/my-extension', 'hybridcore/demo', 'a/b'] as $id) {
            $manifest = array_merge($this->valid(), ['id' => $id]);
            $result = $this->validator->validate($manifest);
            $this->assertTrue($result['valid'], "ID '{$id}' should be valid");
        }
    }

    public function test_empty_manifest_fails(): void
    {
        $result = $this->validator->validate([]);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(2, count($result['errors']));
    }
}
