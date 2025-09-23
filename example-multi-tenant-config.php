<?php

/**
 * Example configuration for multi-tenant global search
 * 
 * Copy this to your config/global-search.php and customize as needed
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Meilisearch Client Configuration
    |--------------------------------------------------------------------------
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
    */
    'federation' => [
        'indexes' => [
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
    */
    'mappings' => [
        // Product mapping for multi-tenant setup
        [
            'model' => App\Models\Product::class,
            'index' => 'products',
            'primary_key' => 'id',
            'fields' => [
                'id', 'slug', 'title', 'sku', 'brand', 'category', 
                'price', 'status', 'description', 'tenant_id', 'created_at', 'updated_at'
            ],
            'computed' => [
                'thumbnail' => fn($model) => $model->thumbnail_url ?? null,
                'url' => fn($model) => route('products.show', $model->slug),
                'search_tags' => fn($model) => $model->tags->pluck('name')->values()->all(),
                'formatted_price' => fn($model) => '$' . number_format($model->price, 2),
            ],
            'filterable' => ['status', 'brand', 'category', 'price', 'tenant_id'],
            'sortable' => ['price', 'created_at', 'updated_at'],
        ],

        // Page mapping
        [
            'model' => App\Models\Page::class,
            'index' => 'pages',
            'primary_key' => 'id',
            'fields' => ['id', 'slug', 'title', 'excerpt', 'content', 'tenant_id', 'created_at', 'updated_at'],
            'computed' => [
                'url' => fn($model) => route('pages.show', $model->slug),
                'excerpt' => fn($model) => Str::limit(strip_tags($model->content), 200),
            ],
            'filterable' => ['created_at', 'tenant_id'],
            'sortable' => ['created_at', 'title'],
        ],

        // User mapping
        [
            'model' => App\Models\User::class,
            'index' => 'users',
            'primary_key' => 'id',
            'fields' => ['id', 'name', 'email', 'tenant_id', 'created_at', 'updated_at'],
            'computed' => [
                'url' => fn($model) => route('users.show', $model->id),
                'avatar' => fn($model) => $model->avatar_url ?? null,
            ],
            'filterable' => ['created_at', 'tenant_id'],
            'sortable' => ['name', 'created_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Settings
    |--------------------------------------------------------------------------
    */
    'index_settings' => [
        'products' => [
            'searchableAttributes' => ['title', 'sku', 'brand', 'category', 'search_tags', 'description'],
            'displayedAttributes' => ['*'],
            'filterableAttributes' => ['status', 'brand', 'category', 'price', 'tenant_id'],
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
            'filterableAttributes' => ['created_at', 'tenant_id'],
            'attributesToHighlight' => ['title', 'excerpt'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 6]
            ],
        ],
        'users' => [
            'searchableAttributes' => ['name', 'email'],
            'displayedAttributes' => ['*'],
            'filterableAttributes' => ['created_at', 'tenant_id'],
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
    */
    'pipeline' => [
        'queue' => env('GLOBAL_SEARCH_QUEUE', 'default'),
        'batch_size' => env('GLOBAL_SEARCH_BATCH_SIZE', 1000),
        'max_jobs_per_tick' => env('GLOBAL_SEARCH_MAX_JOBS_PER_TICK', 5),
        'soft_delete' => env('GLOBAL_SEARCH_SOFT_DELETE', true),
        'retry_attempts' => env('GLOBAL_SEARCH_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('GLOBAL_SEARCH_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multi-tenancy support for the global search package.
    | When enabled, each tenant will have isolated search indexes.
    |
    */
    'tenant' => [
        'enabled' => env('GLOBAL_SEARCH_MULTI_TENANT', false),
        'index_separator' => env('GLOBAL_SEARCH_TENANT_SEPARATOR', '_'),
        
        // Tenant resolution strategies (checked in order)
        'strategies' => [
            [
                'type' => 'subdomain',
                'pattern' => '^([^.]+)\.',
                'exclude' => ['www', 'api', 'admin', 'app'],
            ],
            [
                'type' => 'header',
                'header' => 'X-Tenant-ID',
            ],
            [
                'type' => 'route',
                'parameter' => 'tenant',
            ],
            // Custom resolver example
            [
                'type' => 'custom',
                'resolver' => function() {
                    return auth()->user()?->tenant_id;
                },
            ],
        ],
        
        // Tenant source for getting all tenants
        'source' => env('GLOBAL_SEARCH_TENANT_SOURCE', 'database'), // 'database', 'config', 'custom'
        
        // Database configuration (when source is 'database')
        'model' => env('GLOBAL_SEARCH_TENANT_MODEL', 'App\\Models\\Tenant'),
        'identifier_column' => env('GLOBAL_SEARCH_TENANT_IDENTIFIER', 'id'),
        
        // Configuration list (when source is 'config')
        'list' => [
            'tenant1', 'tenant2', 'tenant3'
        ],
        
        // Custom resolver for getting all tenants (when source is 'custom')
        'list_resolver' => null, // function() { return ['tenant1', 'tenant2']; }
    ],
];
