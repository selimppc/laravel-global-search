<?php

namespace Selimppc\GlobalSearch\Traits;

use Selimppc\GlobalSearch\Jobs\IndexModels;
use Selimppc\GlobalSearch\Jobs\DeleteModels;

trait Searchable
{
    public static function bootSearchable(): void
    {
        static::created(function ($model) {
            dispatch(new IndexModels([get_class($model) => [$model->getKey()]]))
            ->onQueue(config('global-search.pipeline.queue'));
        });

        static::updated(function ($model) {
            dispatch(new IndexModels([get_class($model) => [$model->getKey()]]))
            ->onQueue(config('global-search.pipeline.queue'));
        });

        static::deleted(function ($model) {
            dispatch(new DeleteModels([get_class($model) => [$model->getKey()]]))
            ->onQueue(config('global-search.pipeline.queue'));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                dispatch(new IndexModels([get_class($model) => [$model->getKey()]]))
                ->onQueue(config('global-search.pipeline.queue'));
            });
        }
    }
}
