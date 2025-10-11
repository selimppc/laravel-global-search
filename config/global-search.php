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
        'max_limit' => env('GLOBAL_SEARCH_MAX_LIMIT', 100),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Data Transformation Configuration
    |--------------------------------------------------------------------------
    |
    | Control how data is transformed during indexing.
    |
    */
    'transformation' => [
        'add_metadata' => env('GLOBAL_SEARCH_ADD_METADATA', true), // Add _search_metadata field
        'add_tenant_id' => env('GLOBAL_SEARCH_ADD_TENANT_ID', true), // Add tenant_id field
        'clean_null_values' => env('GLOBAL_SEARCH_CLEAN_NULLS', false), // Remove null values
        'clean_empty_strings' => env('GLOBAL_SEARCH_CLEAN_EMPTY', false), // Remove empty strings
        'max_relationship_items' => env('GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS', 10), // Max items in relationships
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Mappings
    |--------------------------------------------------------------------------
    |
    | Define how your Eloquent models should be mapped to search indexes.
    | Each mapping specifies which fields to index and how to transform them.
    |
    | IMPORTANT: For filtering to work, you MUST:
    | 1. Add fields to the 'fields' array that you want to search/filter
    | 2. Add filterable fields to the 'filterable' array
    | 3. Add corresponding 'filterableAttributes' in the index_settings below
    | 4. Run: php artisan search:sync-settings && php artisan search:reindex
    |
    | FILTERING EXAMPLES:
    | - Basic: filters[status]=active
    | - Multiple: filters[user_type]=Client&filters[is_approved]=1
    | - Arrays: filters[category][]=electronics&filters[category][]=phones
    | - Date ranges: filters[created_at][gte]=2024-01-01&filters[created_at][lte]=2024-12-31
    |
    */
    'mappings' => [
        // Example Product mapping - replace with your actual models
        [
            'model' => App\Models\Product::class,
            'index' => 'products',
            'primaryKey' => 'id',
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
            'primaryKey' => 'id',
            'fields' => ['id', 'slug', 'title', 'excerpt', 'content', 'created_at', 'updated_at'],
            'computed' => [
                'url' => fn($model) => route('pages.show', $model->slug),
                'excerpt' => fn($model) => Str::limit(strip_tags($model->content), 200),
            ],
            'filterable' => ['created_at'],
            'sortable' => ['created_at', 'title'],
        ],

        // Example User mapping - Replace with your actual User model
        [
            'model' => App\Models\User::class, // Change to your User model
            'index' => 'users',
            'primaryKey' => 'id',
            'fields' => [
                'id', 'name', 'email', 'created_at', 'updated_at'
                // Add your custom fields here: 'user_type', 'status', 'phone', etc.
            ],
            'computed' => [
                'url' => fn($model) => route('users.show', $model->id),
                'avatar' => fn($model) => $model->avatar_url ?? null,
            ],
            'filterable' => [
                'created_at', 'updated_at'
                // Add your filterable fields here: 'user_type', 'status', 'is_approved', etc.
            ],
            'sortable' => ['name', 'created_at', 'updated_at'],
        ],

        // Example Product mapping - Replace with your actual Product model
        [
            'model' => App\Models\Product::class, // Change to your Product model
            'index' => 'products',
            'primaryKey' => 'id',
            'fields' => [
                'id', 'name', 'description', 'price', 'status', 'created_at', 'updated_at'
                // Add your custom fields here: 'category', 'brand', 'sku', etc.
            ],
            'computed' => [
                'url' => fn($model) => route('products.show', $model->id),
                'thumbnail' => fn($model) => $model->thumbnail_url ?? null,
            ],
            'filterable' => [
                'status', 'price', 'created_at', 'updated_at'
                // Add your filterable fields here: 'category', 'brand', 'in_stock', etc.
            ],
            'sortable' => ['name', 'price', 'created_at', 'updated_at'],
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
    | CRITICAL FOR FILTERING:
    | - filterableAttributes: Fields that can be used in filters
    | - sortableAttributes: Fields that can be used for sorting
    | - searchableAttributes: Fields that are searched when querying
    |
    | After changing these settings, run:
    | php artisan search:sync-settings && php artisan search:reindex
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
            'filterableAttributes' => ['created_at', 'updated_at'],
            'sortableAttributes' => ['name', 'created_at', 'updated_at'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 6]
            ],
        ],
        'products' => [
            'searchableAttributes' => ['name', 'description'],
            'displayedAttributes' => ['*'],
            'filterableAttributes' => ['status', 'price', 'created_at', 'updated_at'],
            'sortableAttributes' => ['name', 'price', 'created_at', 'updated_at'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8]
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
        'store' => env('GLOBAL_SEARCH_CACHE_STORE', null), // null = use default cache driver
        'ttl' => env('GLOBAL_SEARCH_CACHE_TTL', 60), // seconds
        'version_key_prefix' => env('GLOBAL_SEARCH_CACHE_PREFIX', 'gs:index:'),
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
        'chunk_size' => env('GLOBAL_SEARCH_CHUNK_SIZE', 100), // Models per chunk when indexing
        'max_jobs_per_tick' => env('GLOBAL_SEARCH_MAX_JOBS_PER_TICK', 5),
        'soft_delete' => env('GLOBAL_SEARCH_SOFT_DELETE', true),
        'retry_attempts' => env('GLOBAL_SEARCH_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('GLOBAL_SEARCH_RETRY_DELAY', 500), // milliseconds
        'max_retry_wait' => env('GLOBAL_SEARCH_MAX_RETRY_WAIT', 30), // Max attempts for index creation (increased to 30)
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring and metrics collection.
    |
    */
    'performance' => [
        'enabled' => env('GLOBAL_SEARCH_PERFORMANCE_MONITORING', true),
        'max_metrics_entries' => env('GLOBAL_SEARCH_MAX_METRICS', 100), // Max metrics to store
        'log_slow_queries' => env('GLOBAL_SEARCH_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
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
            // Add custom resolver if needed
            // [
            //     'type' => 'custom',
            //     'resolver' => function() {
            //         return auth()->user()?->tenant_id;
            //     },
            // ],
        ],
        
        // Tenant source for getting all tenants
        'source' => env('GLOBAL_SEARCH_TENANT_SOURCE', 'database'), // 'database', 'config', 'custom'
        
        // Database configuration (when source is 'database')
        'model' => env('GLOBAL_SEARCH_TENANT_MODEL', 'App\\Models\\Tenant'),
        'identifier_column' => env('GLOBAL_SEARCH_TENANT_IDENTIFIER', 'id'),
        
        // Configuration list (when source is 'config')
        'list' => [
            // 'tenant1', 'tenant2', 'tenant3'
        ],
        
        // Custom resolver for getting all tenants (when source is 'custom')
        'list_resolver' => null, // function() { return ['tenant1', 'tenant2']; }
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for failed requests.
    |
    */
    'retry' => [
        'max_retries' => env('GLOBAL_SEARCH_MAX_RETRIES', 3),
        'retry_delay' => env('GLOBAL_SEARCH_RETRY_DELAY', 1000), // milliseconds
        'exponential_backoff' => env('GLOBAL_SEARCH_EXPONENTIAL_BACKOFF', true),
        'retry_on_status' => [500, 502, 503, 504, 429],
        'circuit_breaker_threshold' => env('GLOBAL_SEARCH_CIRCUIT_BREAKER_THRESHOLD', 5),
        'circuit_breaker_timeout' => env('GLOBAL_SEARCH_CIRCUIT_BREAKER_TIMEOUT', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring and optimization.
    |
    */
    'performance' => [
        'monitoring_enabled' => env('GLOBAL_SEARCH_MONITORING_ENABLED', true),
        'max_search_history' => env('GLOBAL_SEARCH_MAX_SEARCH_HISTORY', 1000),
        'max_metrics_history' => env('GLOBAL_SEARCH_MAX_METRICS_HISTORY', 1000),
        'slow_query_threshold' => env('GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD', 1.0), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security features and validation.
    |
    */
    'security' => [
        'max_query_length' => env('GLOBAL_SEARCH_MAX_QUERY_LENGTH', 1000),
        'dangerous_patterns' => [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i'
        ],
        'rate_limiting' => [
            'enabled' => env('GLOBAL_SEARCH_RATE_LIMITING_ENABLED', false),
            'max_requests_per_minute' => env('GLOBAL_SEARCH_RATE_LIMIT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search analytics and insights.
    |
    */
    'analytics' => [
        'enabled' => env('GLOBAL_SEARCH_ANALYTICS_ENABLED', true),
        'retention_days' => env('GLOBAL_SEARCH_ANALYTICS_RETENTION_DAYS', 30),
        'track_zero_results' => env('GLOBAL_SEARCH_TRACK_ZERO_RESULTS', true),
        'track_popular_queries' => env('GLOBAL_SEARCH_TRACK_POPULAR_QUERIES', true),
    ],
];
