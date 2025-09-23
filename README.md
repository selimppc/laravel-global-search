# Laravel Global Search

A modern, minimal Laravel package for global search functionality with Meilisearch integration. **No complex setup required** - just add the trait to your models and you're ready to go!

## 🚀 Quick Start

### 1. Install the Package

```bash
composer require laravel-global-search/global-search
```

**Note:** This package requires Meilisearch. If you don't have it installed, you can install it via:

```bash
# Using Docker
docker run -it --rm -p 7700:7700 getmeili/meilisearch:latest

# Or install Meilisearch directly
curl -L https://install.meilisearch.com | sh
./meilisearch --master-key="your-master-key"
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=global-search-config
```

### 3. Configure Meilisearch

Add to your `.env`:

```env
GLOBAL_SEARCH_HOST=http://localhost:7700
GLOBAL_SEARCH_KEY=your-master-key
```

### 4. Add to Your Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;

class User extends Model
{
    use Searchable;
    
    // That's it! No getTenantId() method needed!
    // The package automatically detects tenant context
}
```

### 5. Search Your Data

```php
// In your controller
$results = User::search('john doe');

// Or use the search service directly
$results = app('global-search')->search('john doe');
```

### 6. Add Search API Route

```php
// In your routes/api.php
Route::get('/search', function() {
    return app('global-search')->search(request('q'));
});
```

## 🎯 Features

- **Zero Configuration**: Just add the trait and search works
- **Auto Tenant Detection**: No need to add `getTenantId()` to models
- **Job-Based Operations**: All indexing happens in background jobs for scale
- **Multi-Tenant Ready**: Automatic tenant context detection
- **Modern PHP**: Uses latest PHP features for minimal code
- **Error-First Logging**: Only logs errors, minimal info logging
- **Performance Optimized**: Built for scale with job queues
- **Health Monitoring**: Built-in health checks and monitoring
- **Error Resilience**: Automatic retry logic and circuit breaker
- **Advanced Search**: Fuzzy search, highlighting, and autocomplete

## 📖 Usage

### Basic Search

```php
// Search users
$users = User::search('john');

// Search with filters
$users = User::search('john', ['status' => 'active']);

// Search with limit
$users = User::search('john', [], 20);
```

### Advanced Search

```php
// Use the search service directly
$searchService = app('global-search');

// Search across all indexes
$results = $searchService->search('john doe');

// Search with tenant context
$results = $searchService->search('john doe', [], 10, 'tenant-1');
```

### Manual Indexing

```php
// Index a single model
$user->searchable();

// Remove from search index
$user->unsearchable();

// Reindex all models
User::reindexAll();
```

## 🔧 Configuration

The configuration is minimal and intuitive:

```php
// config/global-search.php
return [
    // Meilisearch connection
    'client' => [
        'host' => env('GLOBAL_SEARCH_HOST', 'http://localhost:7700'),
        'key' => env('GLOBAL_SEARCH_KEY'),
        'timeout' => 5,
    ],

    // Search indexes
    'indexes' => [
        'users' => ['weight' => 2.0],
        'products' => ['weight' => 1.0],
    ],

    // Model mappings
    'mappings' => [
        [
            'model' => \App\Models\User::class,
            'index' => 'users',
            'fields' => ['id', 'name', 'email', 'created_at'],
        ],
    ],

    // Multi-tenancy (optional)
    'tenant' => [
        'enabled' => env('GLOBAL_SEARCH_MULTI_TENANT', false),
        'model' => \App\Models\Tenant::class,
        'identifier_column' => 'id',
    ],

    // Job configuration
    'pipeline' => [
        'queue' => 'search',
        'batch_size' => 100,
    ],
];
```

## 🏢 Multi-Tenancy

The package automatically detects tenant context from:

1. **Subdomain**: `tenant1.yourdomain.com`
2. **Header**: `X-Tenant-ID: tenant1`
3. **Route**: `/search?tenant=tenant1`
4. **Auth User**: `auth()->user()->tenant_id`
5. **Default**: First available tenant

**No model changes required!** The package handles everything automatically.

### Enable Multi-Tenancy

```php
// In your config
'tenant' => [
    'enabled' => true,
    'model' => \App\Models\Tenant::class,
    'identifier_column' => 'id',
],
```

### Multi-Tenant Features

- **Complete Tenant Isolation**: Each tenant has separate search indexes
- **Automatic Tenant Detection**: No need to manually specify tenant context
- **Flexible Resolution Strategies**: Support for multiple tenant detection methods
- **Backward Compatibility**: Single-tenant setups work without changes
- **Tenant-Specific Commands**: Commands work with specific tenants or all tenants

## 🚀 Scaling with Jobs

All indexing operations use background jobs for scale:

```php
// These automatically use jobs
$user->searchable();        // Queued
$user->unsearchable();      // Queued
User::reindexAll();         // Queued

// Run the queue worker
php artisan queue:work --queue=search
```

## 📊 API Endpoints

### Search API

```http
GET /global-search?q=john&limit=10&tenant=tenant1
```

Response:
```json
{
    "success": true,
    "data": {
        "hits": [
            {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com",
                "url": "/users/1",
                "type": "User"
            }
        ],
        "meta": {
            "total": 1,
            "indexes": ["users"],
            "query": "john",
            "limit": 10,
            "tenant": "tenant1"
        }
    }
}
```

## 🛠️ Commands

```bash
# Reindex all models
php artisan search:reindex

# Reindex specific tenant
php artisan search:reindex-tenant tenant1

# Sync index settings
php artisan search:sync-settings

# Flush all documents
php artisan search:flush

# Flush specific tenant
php artisan search:flush tenant1

# Check index status
php artisan search:status

# Check index status with details
php artisan search:status --detailed

# Check system health
php artisan search:health
```

## 🔍 How It Works

1. **Auto-Detection**: The package automatically detects tenant context
2. **Job-Based**: All operations use background jobs for scale
3. **Minimal Code**: Just add the trait to your models
4. **Error-First**: Only logs errors, minimal info logging
5. **Modern PHP**: Uses latest PHP features for clean code

## 🚨 Error Handling

The package handles all errors gracefully:

- **Meilisearch Down**: Returns empty results, logs error
- **Index Missing**: Creates index automatically
- **Tenant Issues**: Falls back to default tenant
- **Job Failures**: Retries with exponential backoff

## 📈 Performance

- **Background Jobs**: All indexing happens in background
- **Batch Processing**: Processes models in batches
- **Caching**: Intelligent caching with TTL
- **Minimal Logging**: Only logs errors for performance

## 🔒 Security

- **Input Validation**: All inputs are validated
- **Query Sanitization**: Prevents malicious queries
- **Rate Limiting**: Built-in rate limiting support
- **Tenant Isolation**: Complete tenant data isolation

## 🔧 Advanced Features

### Health Monitoring

```bash
# Check system health
php artisan search:health

# Check with detailed output
php artisan search:health --detailed
```

### Error Handling & Resilience

The package includes comprehensive error handling:

- **Automatic Retry Logic**: Failed operations are retried with exponential backoff
- **Circuit Breaker**: Prevents cascading failures when Meilisearch is down
- **Graceful Degradation**: Returns empty results instead of crashing
- **Error Logging**: Only logs actual errors, minimal info logging

### Performance Optimizations

- **Background Jobs**: All operations use queues for scale
- **Batch Processing**: Processes models in configurable batches
- **Intelligent Caching**: Caches search results with TTL
- **Memory Management**: Optimized for large datasets

## 🧪 Testing

```bash
# Run tests
php artisan test

# Run with coverage
php artisan test --coverage
```

## 📝 Logging

The package uses minimal logging:

- **ERROR**: Only logs actual errors
- **No INFO**: No unnecessary info logging
- **Context**: Includes relevant context in error logs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🆘 Support

- **Documentation**: Check this README
- **Issues**: Open an issue on GitHub
- **Discussions**: Use GitHub Discussions

## 🎉 That's It!

You now have a fully functional global search system with:

- ✅ **Zero configuration** for basic usage
- ✅ **Auto tenant detection** - no model changes needed
- ✅ **Job-based operations** for scale
- ✅ **Modern PHP** with minimal code
- ✅ **Error-first logging** for performance
- ✅ **Multi-tenant ready** out of the box

Happy searching! 🔍