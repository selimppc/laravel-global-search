<?php

namespace LaravelGlobalSearch\GlobalSearch;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;
use LaravelGlobalSearch\GlobalSearch\Http\Controllers\GlobalSearchController;
use LaravelGlobalSearch\GlobalSearch\Console\ReindexCommand;
use LaravelGlobalSearch\GlobalSearch\Console\ReindexTenantCommand;
use LaravelGlobalSearch\GlobalSearch\Console\SyncSettingsCommand;
use LaravelGlobalSearch\GlobalSearch\Console\FlushCommand;
use LaravelGlobalSearch\GlobalSearch\Console\HealthCommand;

/**
 * Modern, minimal service provider.
 */
class GlobalSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/global-search.php', 'global-search');
        
        $this->app->singleton(Client::class, fn($app) => new Client(
            $app['config']['global-search.client.host'],
            $app['config']['global-search.client.key'] ?? null
        ));
        
        $this->app->singleton(TenantResolver::class, fn($app) => new TenantResolver(
            $app['config']['global-search']
        ));
        
        $this->app->singleton(GlobalSearchService::class, fn($app) => new GlobalSearchService(
            $app[TenantResolver::class],
            $app['config']['global-search']
        ));
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->registerRoutes();
        $this->registerCommands();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/global-search.php' => config_path('global-search.php'),
        ], 'global-search-config');
    }

    private function registerRoutes(): void
    {
        Route::get('global-search', GlobalSearchController::class)
            ->name('global-search.search');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReindexCommand::class,
                ReindexTenantCommand::class,
                SyncSettingsCommand::class,
                FlushCommand::class,
                HealthCommand::class,
            ]);
        }
    }

}
