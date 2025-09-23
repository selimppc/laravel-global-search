<?php

namespace LaravelGlobalSearch\GlobalSearch\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Bus;
use LaravelGlobalSearch\GlobalSearch\Jobs\IndexModelsJob;
use LaravelGlobalSearch\GlobalSearch\Jobs\DeleteModelsJob;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;

/**
 * Trait that makes Eloquent models searchable by automatically indexing them.
 */
trait Searchable
{
    /**
     * Boot the searchable trait.
     */
    public static function bootSearchable(): void
    {
        static::created(function (Model $model) {
            static::scheduleModelIndexing($model);
        });

        static::updated(function (Model $model) {
            static::scheduleModelIndexing($model);
        });

        static::deleted(function (Model $model) {
            static::scheduleModelDeletion($model);
        });

        // Handle soft delete restoration if the model uses SoftDeletes
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::scheduleModelIndexing($model);
            });
        }
    }

    /**
     * Schedule a model for indexing.
     */
    protected static function scheduleModelIndexing(Model $model): void
    {
        try {
            $queueName = Config::get('global-search.pipeline.queue', 'default');
            $tenantId = static::getModelTenantId($model);
            
            Bus::dispatch(new IndexModelsJob([
                get_class($model) => [$model->getKey()]
            ], $tenantId))->onQueue($queueName);
            
        } catch (\Exception $e) {
            Log::error('Failed to schedule model indexing', [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule a model for deletion from search index.
     */
    protected static function scheduleModelDeletion(Model $model): void
    {
        try {
            $queueName = Config::get('global-search.pipeline.queue', 'default');
            $tenantId = static::getModelTenantId($model);
            
            Bus::dispatch(new DeleteModelsJob([
                get_class($model) => [$model->getKey()]
            ], $tenantId))->onQueue($queueName);
            
        } catch (\Exception $e) {
            Log::error('Failed to schedule model deletion', [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Manually trigger indexing for this model.
     */
    public function searchable(): void
    {
        static::scheduleModelIndexing($this);
    }

    /**
     * Manually trigger deletion from search index for this model.
     */
    public function unsearchable(): void
    {
        static::scheduleModelDeletion($this);
    }

    /**
     * Get the tenant ID for a model.
     * 
     * This method can be overridden in your model to provide custom tenant resolution.
     * By default, it looks for a 'tenant_id' attribute or uses the tenant resolver.
     */
    protected static function getModelTenantId(Model $model): ?string
    {
        // Check if model has a tenant_id attribute
        if ($model->hasAttribute('tenant_id') && $model->tenant_id) {
            return (string) $model->tenant_id;
        }

        // Check if model has a getTenantId method
        if (method_exists($model, 'getTenantId')) {
            return $model->getTenantId();
        }

        // Use the tenant resolver as fallback
        try {
            $tenantResolver = App::make(TenantResolver::class);
            return $tenantResolver->getCurrentTenant();
        } catch (\Exception $e) {
            Log::warning('Failed to resolve tenant for model', [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
