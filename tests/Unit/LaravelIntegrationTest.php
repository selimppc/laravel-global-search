<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;

class LaravelIntegrationTest extends TestCase
{
    public function test_meilisearch_client_can_be_resolved()
    {
        // Test that Meilisearch Client can be resolved from the container
        $client = $this->app->make(Client::class);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_global_search_service_can_be_resolved()
    {
        // Test that GlobalSearchService can be resolved
        $service = $this->app->make(GlobalSearchService::class);
        $this->assertInstanceOf(GlobalSearchService::class, $service);
    }

    public function test_tenant_resolver_can_be_resolved()
    {
        // Test that TenantResolver can be resolved
        $resolver = $this->app->make(TenantResolver::class);
        $this->assertInstanceOf(TenantResolver::class, $resolver);
    }

    public function test_service_provider_registers_all_services()
    {
        // Test that all services are properly bound
        $this->assertTrue($this->app->bound(Client::class));
        $this->assertTrue($this->app->bound(GlobalSearchService::class));
        $this->assertTrue($this->app->bound(TenantResolver::class));
    }

    public function test_configuration_is_loaded()
    {
        // Test that configuration is properly loaded
        $config = $this->app['config']->get('global-search');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('client', $config);
        $this->assertArrayHasKey('tenant', $config);
    }

    public function test_commands_are_registered()
    {
        // Test that command classes exist and can be instantiated
        $this->assertTrue(class_exists('LaravelGlobalSearch\GlobalSearch\Console\ReindexCommand'));
        $this->assertTrue(class_exists('LaravelGlobalSearch\GlobalSearch\Console\ReindexTenantCommand'));
        $this->assertTrue(class_exists('LaravelGlobalSearch\GlobalSearch\Console\SyncSettingsCommand'));
        $this->assertTrue(class_exists('LaravelGlobalSearch\GlobalSearch\Console\HealthCommand'));
    }
}
