<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Illuminate\Support\Facades\App;

class GlobalSearchServiceTest extends TestCase
{
    public function test_search_service_can_be_resolved()
    {
        $service = App::make(GlobalSearchService::class);
        $this->assertInstanceOf(GlobalSearchService::class, $service);
    }

    public function test_search_returns_empty_result_for_empty_query()
    {
        $service = App::make(GlobalSearchService::class);
        $result = $service->search('');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEmpty($result['hits']);
        $this->assertEquals(0, $result['meta']['total']);
    }

    public function test_search_handles_errors_gracefully()
    {
        $service = App::make(GlobalSearchService::class);
        
        // This should not throw an exception even if Meilisearch is not available
        $result = $service->search('test query');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('meta', $result);
    }

    public function test_tenant_resolver_works()
    {
        $resolver = App::make(TenantResolver::class);
        
        $this->assertInstanceOf(TenantResolver::class, $resolver);
        $this->assertNull($resolver->getCurrentTenant());
    }

    public function test_index_name_generation()
    {
        $resolver = App::make(TenantResolver::class);
        
        $baseIndexName = 'test_index';
        
        $indexName = $resolver->getTenantIndexName($baseIndexName);
        
        // Since no tenant is set in test environment, should return base name
        $this->assertEquals('test_index', $indexName);
    }
}
