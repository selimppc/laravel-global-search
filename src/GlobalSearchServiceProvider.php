<?php

namespace LaravelGlobalSearch\GlobalSearch;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\MeilisearchClient;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Support\DefaultTenantResolver;

class GlobalSearchServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/global-search.php', 'global-search');

        $this->registerMeilisearchClient();
        $this->registerTenantResolver();
        $this->registerSearchIndexManager();
        $this->registerGlobalSearchService();
    }

    /**
     * Register the Meilisearch client.
     */
    protected function registerMeilisearchClient(): void
    {
        $this->app->singleton(MeilisearchClient::class, function ($app) {
            $config = $app['config']['global-search.client'];
            
            return new MeilisearchClient(
                $config['host'],
                $config['key'] ?? null,
                (int) ($config['timeout'] ?? 5)
            );
        });
    }

    /**
     * Register the tenant resolver.
     */
    protected function registerTenantResolver(): void
    {
        $this->app->singleton(TenantResolver::class, function ($app) {
            $config = $app['config']['global-search'];
            $customResolver = $config['tenant']['resolver'] ?? null;
            
            if ($customResolver && is_callable($customResolver)) {
                return $customResolver($app);
            }
            
            return new DefaultTenantResolver($config);
        });
    }

    /**
     * Register the search index manager.
     */
    protected function registerSearchIndexManager(): void
    {
        $this->app->singleton(SearchIndexManager::class, function ($app) {
            return new SearchIndexManager(
                $app[MeilisearchClient::class],
                $app[TenantResolver::class],
                $app['config']['global-search']
            );
        });
    }

    /**
     * Register the global search service.
     */
    protected function registerGlobalSearchService(): void
    {
        $this->app->singleton(GlobalSearchService::class, function ($app) {
            return new GlobalSearchService(
                $app[SearchIndexManager::class],
                $app[TenantResolver::class],
                $app['cache.store'],
                $app['config']['global-search']
            );
        });
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->publishAssets();
        $this->registerCommands();
    }

    /**
     * Load package routes.
     */
    protected function loadRoutes(): void
    {
        // For Laravel 12, ensure API routes are enabled
        if (version_compare(App::version(), '12.0.0', '>=')) {
            $this->ensureApiRoutesEnabled();
        }
        
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    /**
     * Ensure API routes are enabled for Laravel 12+.
     */
    protected function ensureApiRoutesEnabled(): void
    {
        $apiRoutesPath = App::basePath('routes/api.php');
        
        // Create api.php if it doesn't exist
        if (!file_exists($apiRoutesPath)) {
            $this->createApiRoutesFile($apiRoutesPath);
            Log::info('Global Search: Created routes/api.php for Laravel 12 compatibility');
        }
        
        // Check if bootstrap/app.php has API routes enabled
        $this->checkBootstrapConfiguration();
    }

    /**
     * Create the API routes file for Laravel 12.
     */
    protected function createApiRoutesFile(string $path): void
    {
        $content = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n/*\n|--------------------------------------------------------------------------\n| API Routes\n|--------------------------------------------------------------------------\n|\n| Here is where you can register API routes for your application.\n| The global search package routes are automatically loaded here.\n|\n*/\n\n// Global search routes are loaded by the service provider\n";
        
        file_put_contents($path, $content);
    }

    /**
     * Check if bootstrap configuration has API routes enabled.
     */
    protected function checkBootstrapConfiguration(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');
        
        if (!file_exists($bootstrapPath)) {
            return;
        }
        
        $content = file_get_contents($bootstrapPath);
        
        if (!str_contains($content, "api: __DIR__.'/../routes/api.php'")) {
            Log::warning('Global Search: Laravel 12 detected - API routes not enabled in bootstrap/app.php');
            Log::warning('Global Search: Please add this line to withRouting(): api: __DIR__.\'/../routes/api.php\'');
            Log::warning('Global Search: Or run: php artisan global-search:setup-laravel12');
        }
    }

    /**
     * Load package views.
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'global-search');
    }

    /**
     * Publish package assets.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/global-search.php' => config_path('global-search.php'),
        ], 'global-search-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/global-search'),
        ], 'global-search-views');
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \LaravelGlobalSearch\GlobalSearch\Console\SearchReindexCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchFlushCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchSyncSettingsCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchWarmCacheCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchDoctorCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SetupLaravel12Command::class,
                // Multi-tenant commands
                \LaravelGlobalSearch\GlobalSearch\Console\SearchReindexTenantCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchFlushTenantCommand::class,
                \LaravelGlobalSearch\GlobalSearch\Console\SearchListTenantsCommand::class,
            ]);
        }
    }
}