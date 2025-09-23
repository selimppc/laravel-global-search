<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class IntegrationTest extends TestCase
{
    public function test_package_installation_and_configuration()
    {
        // Test that the service provider is loaded
        $this->assertTrue($this->app->bound(GlobalSearchService::class));
        $this->assertTrue($this->app->bound(TenantResolver::class));
        
        // Test that config is loaded
        $config = $this->app['config']->get('global-search');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('client', $config);
        $this->assertArrayHasKey('index_name', $config);
        $this->assertArrayHasKey('models', $config);
        $this->assertArrayHasKey('tenant', $config);
    }

    public function test_searchable_trait_integration()
    {
        // Create a test model with Searchable trait
        $model = new class extends Model {
            use Searchable;
            protected $fillable = ['name', 'email'];
        };
        
        // Test that the trait is properly applied
        $this->assertTrue(in_array(Searchable::class, class_uses_recursive($model)));
        
        // Test that reindexAll method exists and is static
        $this->assertTrue(method_exists($model, 'reindexAll'));
        $this->assertTrue((new \ReflectionMethod($model, 'reindexAll'))->isStatic());
    }

    public function test_tenant_resolver_integration()
    {
        $resolver = App::make(TenantResolver::class);
        
        // Test basic functionality
        $this->assertInstanceOf(TenantResolver::class, $resolver);
        
        // Test tenant detection (should return null in test environment)
        $tenant = $resolver->getCurrentTenant();
        $this->assertNull($tenant);
        
        // Test index name generation
        $indexName = $resolver->getTenantIndexName('test_index');
        $this->assertEquals('test_index', $indexName);
        
        // Test tenant listing
        $tenants = $resolver->getAllTenants();
        $this->assertIsArray($tenants);
    }

    public function test_search_service_integration()
    {
        $service = App::make(GlobalSearchService::class);
        
        // Test search with empty query
        $result = $service->search('');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEmpty($result['hits']);
        
        // Test search with query (should handle gracefully even without Meilisearch)
        $result = $service->search('test query');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('meta', $result);
        
        // Test that service methods exist
        $this->assertTrue(method_exists($service, 'indexModel'));
        $this->assertTrue(method_exists($service, 'deleteModel'));
        $this->assertTrue(method_exists($service, 'reindexAll'));
        $this->assertTrue(method_exists($service, 'flushAll'));
        $this->assertTrue(method_exists($service, 'syncSettings'));
    }

    public function test_commands_are_available()
    {
        // Test that all documented commands exist
        $commands = [
            'search:reindex',
            'search:reindex-tenant', 
            'search:sync-settings',
            'search:health'
        ];
        
        foreach ($commands as $command) {
            $this->assertTrue(
                $this->app->bound("command.{$command}") || 
                class_exists("LaravelGlobalSearch\\GlobalSearch\\Console\\" . $this->getCommandClass($command))
            );
        }
    }
    
    private function getCommandClass(string $command): string
    {
        $map = [
            'search:reindex' => 'ReindexCommand',
            'search:reindex-tenant' => 'ReindexTenantCommand',
            'search:sync-settings' => 'SyncSettingsCommand',
            'search:health' => 'HealthCommand',
        ];
        
        return $map[$command] ?? '';
    }

    public function test_configuration_structure()
    {
        $config = $this->app['config']->get('global-search');
        
        // Test basic configuration structure
        $this->assertIsArray($config);
        $this->assertArrayHasKey('client', $config);
        $this->assertArrayHasKey('tenant', $config);
        
        // Test client configuration
        $this->assertArrayHasKey('host', $config['client']);
        $this->assertArrayHasKey('key', $config['client']);
        $this->assertArrayHasKey('timeout', $config['client']);
        
        // Test tenant configuration
        $this->assertArrayHasKey('enabled', $config['tenant']);
        
        // Test that required values are not null
        $this->assertNotNull($config['client']['host']);
        $this->assertNotNull($config['client']['key']);
        $this->assertNotNull($config['client']['timeout']);
        $this->assertNotNull($config['tenant']['enabled']);
    }
}
