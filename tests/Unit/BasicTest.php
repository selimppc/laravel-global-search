<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class BasicTest extends TestCase
{
    public function test_service_provider_registers_services()
    {
        $this->assertTrue($this->app->bound(GlobalSearchService::class));
        $this->assertTrue($this->app->bound(TenantResolver::class));
    }

    public function test_config_is_loaded()
    {
        $config = $this->app['config']->get('global-search');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('client', $config);
        $this->assertArrayHasKey('index_name', $config);
        $this->assertArrayHasKey('models', $config);
    }

    public function test_tenant_resolver_works()
    {
        $resolver = $this->app->make(TenantResolver::class);
        
        $this->assertInstanceOf(TenantResolver::class, $resolver);
        $this->assertNull($resolver->getCurrentTenant());
    }
}
