# Laravel Global Search - Configuration Guide

This guide provides comprehensive documentation for all configuration options available in the Laravel Global Search package.

## Table of Contents

- [Meilisearch Client](#meilisearch-client)
- [Federation](#federation)
- [Data Transformation](#data-transformation)
- [Model Mappings](#model-mappings)
- [Index Settings](#index-settings)
- [Cache Configuration](#cache-configuration)
- [Pipeline Configuration](#pipeline-configuration)
- [Performance Monitoring](#performance-monitoring)
- [Multi-Tenancy](#multi-tenancy)

---

## Meilisearch Client

Configure your Meilisearch instance connection.

```php
'client' => [
    'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
    'key' => env('MEILISEARCH_KEY'),
    'timeout' => env('MEILISEARCH_TIMEOUT', 5),
],
```

### Environment Variables

```env
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key
MEILISEARCH_TIMEOUT=5
```

---

## Federation

Configure which indexes to search across and their relative weights.

```php
'federation' => [
    'indexes' => [
        'products' => ['weight' => 3.0],
        'pages' => ['weight' => 1.0],
        'users' => ['weight' => 2.0],
    ],
    'default_limit' => env('GLOBAL_SEARCH_DEFAULT_LIMIT', 10),
    'max_limit' => env('GLOBAL_SEARCH_MAX_LIMIT', 100),
],
```

### Options

- **`indexes`**: Array of indexes with their search weights (higher = more priority)
- **`default_limit`**: Default number of results per search query
- **`max_limit`**: Maximum allowed results per query (protects against abuse)

### Environment Variables

```env
GLOBAL_SEARCH_DEFAULT_LIMIT=10
GLOBAL_SEARCH_MAX_LIMIT=100
```

---

## Data Transformation

Control how data is transformed during indexing.

```php
'transformation' => [
    'add_metadata' => env('GLOBAL_SEARCH_ADD_METADATA', true),
    'add_tenant_id' => env('GLOBAL_SEARCH_ADD_TENANT_ID', true),
    'clean_null_values' => env('GLOBAL_SEARCH_CLEAN_NULLS', false),
    'clean_empty_strings' => env('GLOBAL_SEARCH_CLEAN_EMPTY', false),
    'max_relationship_items' => env('GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS', 10),
],
```

### Options

- **`add_metadata`**: Add `_search_metadata` field with model info, indexed_at, URL
- **`add_tenant_id`**: Include tenant_id in indexed documents
- **`clean_null_values`**: Remove null values from indexed data
- **`clean_empty_strings`**: Remove empty strings from indexed data
- **`max_relationship_items`**: Limit number of related items to include

### Environment Variables

```env
GLOBAL_SEARCH_ADD_METADATA=true
GLOBAL_SEARCH_ADD_TENANT_ID=true
GLOBAL_SEARCH_CLEAN_NULLS=false
GLOBAL_SEARCH_CLEAN_EMPTY=false
GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS=10
```

### Examples

#### Disable Metadata (Smaller Index Size)
```env
GLOBAL_SEARCH_ADD_METADATA=false
```

#### Clean Data (Remove Nulls and Empty Strings)
```env
GLOBAL_SEARCH_CLEAN_NULLS=true
GLOBAL_SEARCH_CLEAN_EMPTY=true
```

---

## Model Mappings

Define how your Eloquent models should be mapped to search indexes.

```php
'mappings' => [
    [
        'model' => App\Models\Product::class,
        'index' => 'products',
        'primaryKey' => 'id',
        'fields' => ['id', 'title', 'description', 'price'],
        'computed' => [
            'url' => fn($model) => route('products.show', $model->id),
            'formatted_price' => fn($model) => '$' . number_format($model->price, 2),
        ],
        'filterable' => ['status', 'category', 'price'],
        'sortable' => ['price', 'created_at', 'updated_at'],
        'relationships' => [
            'categories' => [
                'fields' => ['id', 'name'],
                'max_items' => 5, // Override global max_relationship_items
            ],
        ],
    ],
],
```

### Mapping Options

- **`model`**: Fully qualified model class name
- **`index`**: Meilisearch index name
- **`primaryKey`**: Primary key field (default: 'id')
- **`fields`**: Array of fields to index
- **`computed`**: Closures or method names to compute additional fields
- **`filterable`**: Fields that can be filtered
- **`sortable`**: Fields that can be sorted
- **`relationships`**: Related models to include in search

---

## Index Settings

Configure Meilisearch index settings for optimal search behavior.

```php
'index_settings' => [
    'products' => [
        'searchableAttributes' => ['title', 'description', 'sku'],
        'displayedAttributes' => ['*'],
        'filterableAttributes' => ['status', 'category', 'price'],
        'sortableAttributes' => ['price', 'created_at'],
        'typoTolerance' => [
            'enabled' => true,
            'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8]
        ],
        'synonyms' => [
            'phone' => ['mobile', 'cellphone'],
        ],
        'stopWords' => ['and', 'or', 'the'],
    ],
],
```

### Apply Settings

After modifying index settings, run:

```bash
php artisan search:sync-settings
php artisan search:reindex
```

---

## Cache Configuration

Configure caching for search results to improve performance.

```php
'cache' => [
    'enabled' => env('GLOBAL_SEARCH_CACHE_ENABLED', true),
    'store' => env('GLOBAL_SEARCH_CACHE_STORE', null), // null = default cache driver
    'ttl' => env('GLOBAL_SEARCH_CACHE_TTL', 60), // seconds
    'version_key_prefix' => env('GLOBAL_SEARCH_CACHE_PREFIX', 'gs:index:'),
],
```

### Environment Variables

```env
GLOBAL_SEARCH_CACHE_ENABLED=true
GLOBAL_SEARCH_CACHE_STORE=redis
GLOBAL_SEARCH_CACHE_TTL=60
GLOBAL_SEARCH_CACHE_PREFIX=gs:index:
```

### Examples

#### Disable Caching for Development
```env
GLOBAL_SEARCH_CACHE_ENABLED=false
```

#### Use Redis Cache with 5 Minute TTL
```env
GLOBAL_SEARCH_CACHE_STORE=redis
GLOBAL_SEARCH_CACHE_TTL=300
```

---

## Pipeline Configuration

Configure background job processing for indexing operations.

```php
'pipeline' => [
    'queue' => env('GLOBAL_SEARCH_QUEUE', 'default'),
    'batch_size' => env('GLOBAL_SEARCH_BATCH_SIZE', 1000),
    'chunk_size' => env('GLOBAL_SEARCH_CHUNK_SIZE', 100),
    'max_jobs_per_tick' => env('GLOBAL_SEARCH_MAX_JOBS_PER_TICK', 5),
    'soft_delete' => env('GLOBAL_SEARCH_SOFT_DELETE', true),
    'retry_attempts' => env('GLOBAL_SEARCH_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('GLOBAL_SEARCH_RETRY_DELAY', 500), // milliseconds
    'max_retry_wait' => env('GLOBAL_SEARCH_MAX_RETRY_WAIT', 10),
],
```

### Options

- **`queue`**: Laravel queue name for indexing jobs
- **`batch_size`**: Number of models per database query
- **`chunk_size`**: Number of models to process per iteration
- **`max_jobs_per_tick`**: Maximum jobs to dispatch per reindex operation
- **`soft_delete`**: Handle soft-deleted models
- **`retry_attempts`**: Number of retry attempts for failed operations
- **`retry_delay`**: Delay between retries (milliseconds)
- **`max_retry_wait`**: Maximum attempts for index creation

### Environment Variables

```env
GLOBAL_SEARCH_QUEUE=default
GLOBAL_SEARCH_BATCH_SIZE=1000
GLOBAL_SEARCH_CHUNK_SIZE=100
GLOBAL_SEARCH_MAX_JOBS_PER_TICK=5
GLOBAL_SEARCH_SOFT_DELETE=true
GLOBAL_SEARCH_RETRY_ATTEMPTS=3
GLOBAL_SEARCH_RETRY_DELAY=500
GLOBAL_SEARCH_MAX_RETRY_WAIT=10
```

### Performance Tuning

#### High-Volume Indexing (Large Dataset)
```env
GLOBAL_SEARCH_BATCH_SIZE=5000
GLOBAL_SEARCH_CHUNK_SIZE=500
```

#### Low-Memory Environment
```env
GLOBAL_SEARCH_BATCH_SIZE=100
GLOBAL_SEARCH_CHUNK_SIZE=10
```

#### Faster Retries (For Local Development)
```env
GLOBAL_SEARCH_RETRY_DELAY=100
GLOBAL_SEARCH_MAX_RETRY_WAIT=5
```

---

## Performance Monitoring

Configure performance monitoring and metrics collection.

```php
'performance' => [
    'enabled' => env('GLOBAL_SEARCH_PERFORMANCE_MONITORING', true),
    'max_metrics_entries' => env('GLOBAL_SEARCH_MAX_METRICS', 100),
    'log_slow_queries' => env('GLOBAL_SEARCH_LOG_SLOW_QUERIES', true),
    'slow_query_threshold' => env('GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
],
```

### Options

- **`enabled`**: Enable performance monitoring
- **`max_metrics_entries`**: Maximum number of metrics to store in cache
- **`log_slow_queries`**: Log queries that exceed the slow query threshold
- **`slow_query_threshold`**: Threshold in milliseconds to consider a query "slow"

### Environment Variables

```env
GLOBAL_SEARCH_PERFORMANCE_MONITORING=true
GLOBAL_SEARCH_MAX_METRICS=100
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=1000
```

### Examples

#### Aggressive Slow Query Detection
```env
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=500
```

#### Disable Performance Monitoring (Production)
```env
GLOBAL_SEARCH_PERFORMANCE_MONITORING=false
```

### Check Performance

```bash
php artisan search:performance
```

---

## Multi-Tenancy

Configure multi-tenancy support using Stancl/Tenancy.

```php
'tenant' => [
    'enabled' => env('GLOBAL_SEARCH_TENANT_ENABLED', false),
    'identifier_column' => env('GLOBAL_SEARCH_TENANT_IDENTIFIER', 'id'),
    'resolution_order' => [
        'query',      // ?tenant=xxx
        'subdomain',  // tenant.example.com
        'header',     // X-Tenant-ID
        'route',      // /tenant/{tenant}/...
        'auth',       // From authenticated user
        'default',    // Fallback tenant
    ],
    'default_tenant' => env('GLOBAL_SEARCH_DEFAULT_TENANT', null),
],
```

### Environment Variables

```env
GLOBAL_SEARCH_TENANT_ENABLED=true
GLOBAL_SEARCH_TENANT_IDENTIFIER=id
GLOBAL_SEARCH_DEFAULT_TENANT=null
```

### Examples

#### Enable Multi-Tenancy
```env
GLOBAL_SEARCH_TENANT_ENABLED=true
```

#### Use Subdomain Tenant Resolution
The package will automatically detect the tenant from the subdomain.

---

## Complete .env Example

```env
# Meilisearch Connection
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key
MEILISEARCH_TIMEOUT=5

# Federation & Limits
GLOBAL_SEARCH_DEFAULT_LIMIT=10
GLOBAL_SEARCH_MAX_LIMIT=100

# Data Transformation
GLOBAL_SEARCH_ADD_METADATA=true
GLOBAL_SEARCH_ADD_TENANT_ID=true
GLOBAL_SEARCH_CLEAN_NULLS=false
GLOBAL_SEARCH_CLEAN_EMPTY=false
GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS=10

# Cache
GLOBAL_SEARCH_CACHE_ENABLED=true
GLOBAL_SEARCH_CACHE_STORE=redis
GLOBAL_SEARCH_CACHE_TTL=60
GLOBAL_SEARCH_CACHE_PREFIX=gs:index:

# Pipeline
GLOBAL_SEARCH_QUEUE=default
GLOBAL_SEARCH_BATCH_SIZE=1000
GLOBAL_SEARCH_CHUNK_SIZE=100
GLOBAL_SEARCH_MAX_JOBS_PER_TICK=5
GLOBAL_SEARCH_SOFT_DELETE=true
GLOBAL_SEARCH_RETRY_ATTEMPTS=3
GLOBAL_SEARCH_RETRY_DELAY=500
GLOBAL_SEARCH_MAX_RETRY_WAIT=10

# Performance
GLOBAL_SEARCH_PERFORMANCE_MONITORING=true
GLOBAL_SEARCH_MAX_METRICS=100
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=1000

# Multi-Tenancy
GLOBAL_SEARCH_TENANT_ENABLED=false
GLOBAL_SEARCH_TENANT_IDENTIFIER=id
GLOBAL_SEARCH_DEFAULT_TENANT=null
```

---

## Best Practices

### 1. Production Settings
```env
GLOBAL_SEARCH_CACHE_ENABLED=true
GLOBAL_SEARCH_CACHE_STORE=redis
GLOBAL_SEARCH_CACHE_TTL=300
GLOBAL_SEARCH_PERFORMANCE_MONITORING=false
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=500
```

### 2. Development Settings
```env
GLOBAL_SEARCH_CACHE_ENABLED=false
GLOBAL_SEARCH_PERFORMANCE_MONITORING=true
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=100
GLOBAL_SEARCH_RETRY_DELAY=100
```

### 3. High-Volume Applications
```env
GLOBAL_SEARCH_BATCH_SIZE=5000
GLOBAL_SEARCH_CHUNK_SIZE=500
GLOBAL_SEARCH_MAX_LIMIT=50
GLOBAL_SEARCH_CACHE_TTL=600
```

### 4. Low-Memory Environments
```env
GLOBAL_SEARCH_BATCH_SIZE=100
GLOBAL_SEARCH_CHUNK_SIZE=10
GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS=5
GLOBAL_SEARCH_CLEAN_NULLS=true
```

---

## Troubleshooting

### Slow Indexing
Increase batch and chunk sizes:
```env
GLOBAL_SEARCH_BATCH_SIZE=5000
GLOBAL_SEARCH_CHUNK_SIZE=500
```

### Memory Issues
Decrease batch and chunk sizes:
```env
GLOBAL_SEARCH_BATCH_SIZE=100
GLOBAL_SEARCH_CHUNK_SIZE=10
```

### Stale Search Results
Decrease cache TTL or disable caching:
```env
GLOBAL_SEARCH_CACHE_TTL=30
# or
GLOBAL_SEARCH_CACHE_ENABLED=false
```

### Index Creation Failures
Increase retry attempts and delays:
```env
GLOBAL_SEARCH_RETRY_ATTEMPTS=5
GLOBAL_SEARCH_RETRY_DELAY=1000
GLOBAL_SEARCH_MAX_RETRY_WAIT=20
```

---

## Support

For more information, visit:
- [Main README](README.md)
- [GitHub Issues](https://github.com/laravel-global-search/global-search/issues)

