<?php

namespace LaravelGlobalSearch\GlobalSearch;

use Illuminate\Support\ServiceProvider;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\MeilisearchClient;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

class GlobalSearchServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/global-search.php', 'global-search');

        $this->registerMeilisearchClient();
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
     * Register the search index manager.
     */
    protected function registerSearchIndexManager(): void
    {
        $this->app->singleton(SearchIndexManager::class, function ($app) {
            return new SearchIndexManager(
                $app[MeilisearchClient::class],
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
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
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
            ]);
        }
    }
}