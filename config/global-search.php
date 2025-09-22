<?php

return [
    'client' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'timeout' => 5,
    ],

    'federation' => [
        'indexes' => [
            // index => weight
            'products' => ['weight' => 3],
            'pages' => ['weight' => 1],
        ],
        'default_limit' => 10,
        'max_limit' => 50,
    ],
    'mappings' => [
        // Example mapping; replace with your real models
        [
            'model' => App\Models\Product::class,
            'index' => 'products',
            'primary_key' => 'id',
            'fields' => ['id','slug','title','sku','brand','category','price','status','created_at','updated_at'],
            'computed' => [
                'thumbnail' => fn($m) => $m->thumbnail_url,
                'url' => fn($m) => route('product.show', $m->slug),
                'search_tags' => fn($m) => $m->tags->pluck('name')->values()->all(),
            ],
            'filterable' => ['status','brand','category'],
            'sortable' => ['price','created_at','updated_at'],
            // 'transformer' => App\Search\Transformers\ProductTransformer::class,
        ],
        [
            'model' => App\Models\Page::class,
            'index' => 'pages',
            'primary_key' => 'id',
            'fields' => ['id','slug','title','excerpt','created_at','updated_at'],
            'computed' => [
                'url' => fn($m) => route('page.show', $m->slug),
            ],
            'filterable' => ['created_at'],
            'sortable' => ['created_at'],
        ],
    ],

    'index_settings' => [
        'products' => [
            'searchableAttributes' => ['title','sku','brand','category','search_tags'],
            'displayedAttributes' => ['*'],
            'filterableAttributes' => ['status','brand','category'],
            'sortableAttributes' => ['price','created_at','updated_at'],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8]
            ],
            'synonyms' => [ 'watch' => ['wristwatch','timepiece'] ],
            'stopWords' => ['and','or','the'],
        ],
        'pages' => [
            'searchableAttributes' => ['title','excerpt'],
            'displayedAttributes' => ['*'],
            'attributesToHighlight'=> ['title','excerpt'],
        ],
    ],

    'cache' => [
        'enabled' => true,
        'store' => env('GLOBAL_SEARCH_CACHE_STORE', 'redis'),
        'ttl' => 60,
        'version_key_prefix' => 'ms:index:',
    ],

    'pipeline' => [
        'queue' => env('GLOBAL_SEARCH_QUEUE', 'default'),
        'batch_size' => 1000,
        'max_jobs_per_tick' => 5,
        'soft_delete' => true,
    ],
];
