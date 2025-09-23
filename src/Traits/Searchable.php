<?php

namespace LaravelGlobalSearch\GlobalSearch\Traits;

use LaravelGlobalSearch\GlobalSearch\Jobs\IndexJob;
use LaravelGlobalSearch\GlobalSearch\Jobs\DeleteJob;
use Illuminate\Support\Facades\App;

/**
 * Modern searchable trait - no getTenantId required!
 * Auto-detects tenant context and handles everything automatically.
 */
trait Searchable
{
    protected static function bootSearchable(): void
    {
        // Auto-index on create/update
        static::created(fn($model) => static::scheduleIndexing($model));
        static::updated(fn($model) => static::scheduleIndexing($model));
        
        // Auto-delete on delete
        static::deleted(fn($model) => static::scheduleDeletion($model));
    }

    protected static function scheduleIndexing($model): void
    {
        if (static::shouldIndex($model)) {
            IndexJob::dispatch($model::class, [$model->id])
                ->onQueue('search');
        }
    }

    protected static function scheduleDeletion($model): void
    {
        if (static::shouldIndex($model)) {
            DeleteJob::dispatch($model::class, [$model->id])
                ->onQueue('search');
        }
    }

    protected static function shouldIndex($model): bool
    {
        // Skip indexing if model is soft deleted and not force deleted
        if (method_exists($model, 'trashed') && $model->trashed()) {
            return false;
        }

        // Skip indexing if model has indexing disabled
        if (property_exists($model, 'searchable') && $model->searchable === false) {
            return false;
        }

        return true;
    }

    /**
     * Manually trigger indexing for this model.
     */
    public function searchable(): void
    {
        static::scheduleIndexing($this);
    }

    /**
     * Manually trigger deletion from search index.
     */
    public function unsearchable(): void
    {
        static::scheduleDeletion($this);
    }

    /**
     * Reindex all models of this type.
     */
    public static function reindexAll(): void
    {
        $searchService = App::make(\LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService::class);
        $searchService->reindexAll();
    }
}
