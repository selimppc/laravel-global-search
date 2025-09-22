<?php

namespace LaravelGlobalSearch\GlobalSearch\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Jobs\IndexModelsJob;
use LaravelGlobalSearch\GlobalSearch\Jobs\DeleteModelsJob;

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
            $queueName = config('global-search.pipeline.queue', 'default');
            
            dispatch(new IndexModelsJob([
                get_class($model) => [$model->getKey()]
            ]))->onQueue($queueName);
            
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
            $queueName = config('global-search.pipeline.queue', 'default');
            
            dispatch(new DeleteModelsJob([
                get_class($model) => [$model->getKey()]
            ]))->onQueue($queueName);
            
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
}
