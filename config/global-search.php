<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meilisearch Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Meilisearch instance connection details here.
    |
    */
    'client' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'timeout' => env('MEILISEARCH_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Federation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which indexes to search across and their relative weights.
    | Higher weights mean results from that index will be prioritized.
    |
    */
    'federation' => [
        'indexes' => [
            // Example configuration - replace with your actual indexes
            'products' => ['weight' => 3.0],
            'pages' => ['weight' => 1.0],
            'users' => ['weight' => 2.0],
        ],
        'default_limit' => env('GLOBAL_SEARCH_DEFAULT_LIMIT', 10),
        'max_limit' => env('GLOBAL_SEARCH_MAX_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Mappings
    |--------------------------------------------------------------------------
    |
    | Define how your Eloquent models should be mapped to search indexes.
    | Each mapping specifies which fields to index and how to transform them.
    |
    */
    'mappings' => [
        // Example Product mapping - replace with your actual models
        [
            'model' => App\Models\Product::class,
            'index' => 'products',
            'primary_key' => 'id',
            'fields' => [
                'id', 'slug', 'title', 'sku', 'brand', 'category', 
                'price', 'status', 'description', 'created_at', 'updated_at'
            ],
            'computed' => [
                'thumbnail' => fn($model) => $model->thumbnail_url ?? null,
                'url' => fn($model) => route('products.show', $model->slug),
                'search_tags' => fn($model) => $model->tags->pluck('name')->values()->all(),
                'formatted_price' => fn($model) => '$' . number_format($model->price, 2),
            ],
            'filterable' => ['status', 'brand', 'category', 'price'],
            'sortable' => ['price', 'created_at', 'updated_at'],
            // 'transformer' => App\Search\Transformers\ProductTransformer::class,
        ],

        // Example Page mapping
        [
            'model' => App\Models\Page::class,
            'index' => 'pages',
            'primary_key' => 'id',
            'fields' => ['id', 'slug', 'title', 'excerpt', 'content', 'created_at', 'updated_at'],
            'computed' => [
                'url' => fn($model) => route('pages.show', $model->slug),
                'excerpt' => fn($model) => Str::limit(strip_tags($model->content), 200),
            ],
            'filterable' => ['created_at'],
            'sortable' => ['created_at', 'title'],
        ],

        // Example User mapping
        [
            'model' => App\Models\User::class,
            'index' => 'users',
            'primary_key' => 'id',
            'fields' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'computed' => [
                'url' => fn($model) => route('users.show', $model->id),
                'avatar' => fn($model) => $model->avatar_url ?? null,
            ],
            'filterable' => ['created_at'],
            'sortable' => ['name', 'created_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Settings
    |--------------------------------------------------------------------------
    |
    | Configure Meilisearch index settings for each of your indexes.
    | These settings control how Meilisearch processes and searches your data.
    |
    */
    'index_settings' => [
        'products' => [
            'searchableAttributes' => ['title', 'sku', 'brand', 'category', 'search_tags', 'description'],
            'displayedAttributes' => ['*'],
            'filterableAttributes' => ['status', 'brand', 'category', 'price'],
            'sortableAttributes' => ['price', 'created_at', 'updated_at'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8]
            ],
            'synonyms' => [
                'watch' => ['wristwatch', 'timepiece'],
                'phone' => ['mobile', 'cellphone'],
            ],
            'stopWords' => ['and', 'or', 'the', 'a', 'an'],
        ],
        'pages' => [
            'searchableAttributes' => ['title', 'excerpt', 'content'],
            'displayedAttributes' => ['*'],
            'attributesToHighlight' => ['title', 'excerpt'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 6]
            ],
        ],
        'users' => [
            'searchableAttributes' => ['name', 'email'],
            'displayedAttributes' => ['*'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 6]
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for search results to improve performance.
    | Cache keys are automatically invalidated when indexes are updated.
    |
    */
    'cache' => [
        'enabled' => env('GLOBAL_SEARCH_CACHE_ENABLED', true),
        'store' => env('GLOBAL_SEARCH_CACHE_STORE', 'redis'),
        'ttl' => env('GLOBAL_SEARCH_CACHE_TTL', 60), // minutes
        'version_key_prefix' => 'gs:index:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the background job processing for indexing operations.
    | These settings control how models are queued and processed.
    |
    */
    'pipeline' => [
        'queue' => env('GLOBAL_SEARCH_QUEUE', 'default'),
        'batch_size' => env('GLOBAL_SEARCH_BATCH_SIZE', 1000),
        'max_jobs_per_tick' => env('GLOBAL_SEARCH_MAX_JOBS_PER_TICK', 5),
        'soft_delete' => env('GLOBAL_SEARCH_SOFT_DELETE', true),
        'retry_attempts' => env('GLOBAL_SEARCH_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('GLOBAL_SEARCH_RETRY_DELAY', 60), // seconds
    ],
];
