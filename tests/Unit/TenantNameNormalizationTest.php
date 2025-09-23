<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class TenantNameNormalizationTest extends TestCase
{
    public function test_tenant_name_normalization()
    {
        $config = [
            'tenant' => [
                'enabled' => true,
            ],
        ];

        $resolver = new TenantResolver($config);

        // Test various tenant name normalizations
        $testCases = [
            'Real Estate' => 'real-estate',
            'My Company' => 'my-company',
            'Test@Company#1' => 'test-company-1',
            '  Multiple   Spaces  ' => 'multiple-spaces',
            'Special!@#$%^&*()Chars' => 'special-chars',
            'UPPERCASE' => 'uppercase',
            'mixed-Case_With_Underscores' => 'mixed-case_with_underscores',
            '---Multiple---Hyphens---' => 'multiple-hyphens',
            '123Numbers456' => '123numbers456',
        ];

        foreach ($testCases as $input => $expected) {
            $indexName = $resolver->getTenantIndexName('users', $input);
            $expectedIndexName = "users_{$expected}";
            
            $this->assertEquals($expectedIndexName, $indexName, "Failed to normalize '{$input}' to '{$expected}'");
        }
    }

    public function test_meilisearch_index_uid_compliance()
    {
        $config = [
            'tenant' => [
                'enabled' => true,
            ],
        ];

        $resolver = new TenantResolver($config);

        // Test that normalized names comply with Meilisearch index UID rules
        $problematicNames = [
            'Real Estate',
            'My Company & Co.',
            'Test@Company#1',
            '  Spaces  ',
            'Special!@#$%^&*()Chars',
        ];

        foreach ($problematicNames as $name) {
            $indexName = $resolver->getTenantIndexName('users', $name);
            
            // Check that the index name only contains allowed characters
            $this->assertMatchesRegularExpression('/^[a-z0-9\-_]+$/', $indexName, "Index name '{$indexName}' contains invalid characters");
            
            // Check that it's not too long (Meilisearch limit is 512 bytes)
            $this->assertLessThan(512, strlen($indexName), "Index name '{$indexName}' is too long");
            
            // Check that it doesn't start or end with hyphens
            $this->assertFalse(str_starts_with($indexName, '-'), "Index name '{$indexName}' starts with hyphen");
            $this->assertFalse(str_ends_with($indexName, '-'), "Index name '{$indexName}' ends with hyphen");
        }
    }

    public function test_empty_tenant_name_handling()
    {
        $config = [
            'tenant' => [
                'enabled' => true,
            ],
        ];

        $resolver = new TenantResolver($config);

        // Test empty tenant name
        $indexName = $resolver->getTenantIndexName('users', '');
        $this->assertEquals('users', $indexName);

        // Test null tenant
        $indexName = $resolver->getTenantIndexName('users', null);
        $this->assertEquals('users', $indexName);
    }

    public function test_multi_tenant_disabled()
    {
        $config = [
            'tenant' => [
                'enabled' => false,
            ],
        ];

        $resolver = new TenantResolver($config);

        // When multi-tenancy is disabled, tenant should be ignored
        $indexName = $resolver->getTenantIndexName('users', 'Real Estate');
        $this->assertEquals('users', $indexName);
    }
}
