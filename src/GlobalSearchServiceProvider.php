<?php

namespace Selimppc\GlobalSearch;

use Illuminate\Support\ServiceProvider;
use Selimppc\GlobalSearch\Services\FederatedSearch;
use Selimppc\GlobalSearch\Support\MeiliClient;
use Selimppc\GlobalSearch\Support\IndexManager;

class GlobalSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/global-search.php', 'global-search');

        $this->app->singleton(MeiliClient::class, function ($app) {
            $cfg = $app['config']['global-search.client'];
            return new MeiliClient($cfg['host'], $cfg['key'] ?? null, (int)($cfg['timeout'] ?? 5));
        });

        $this->app->singleton(IndexManager::class, function ($app) {
            return new IndexManager($app[MeiliClient::class], $app['config']['global-search']);
        });

        $this->app->singleton(FederatedSearch::class, function ($app) {
            return new FederatedSearch($app[IndexManager::class], $app['cache.store'], $app['config']['global-search']);
        });
    }

    public function boot(): void
    {
        // Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Views (Blade component optional)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'global-search');

        // Publish config & views
        $this->publishes([
        __DIR__.'/../config/global-search.php' => config_path('global-search.php'),
        ], 'global-search-config');

        $this->publishes([
        __DIR__.'/../resources/views' => resource_path('views/vendor/global-search'),
        ], 'global-search-views');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Selimppc\GlobalSearch\Console\SearchReindex::class,
                \Selimppc\GlobalSearch\Console\SearchFlush::class,
                \Selimppc\GlobalSearch\Console\SearchSyncSettings::class,
                \Selimppc\GlobalSearch\Console\SearchWarmCache::class,
                \Selimppc\GlobalSearch\Console\SearchDoctor::class,
            ]);
        }
    }
}