# Laravel Global Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laravel-global-search/global-search.svg?style=flat-square)](https://packagist.org/packages/laravel-global-search/global-search)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel-global-search/global-search.svg?style=flat-square)](https://packagist.org/packages/laravel-global-search/global-search)
[![License](https://img.shields.io/packagist/l/laravel-global-search/global-search.svg?style=flat-square)](https://packagist.org/packages/laravel-global-search/global-search)

A professional, high-performance global search package for Laravel powered by Meilisearch with federated search capabilities. Search across multiple models and indexes with intelligent ranking, caching, and a beautiful UI component.

## ✨ Features

- **🔍 Federated Search**: Search across multiple models and indexes simultaneously
- **⚡ High Performance**: Built-in caching with automatic cache invalidation
- **🎯 Intelligent Ranking**: Configurable index weights and scoring algorithms
- **🔄 Auto-Indexing**: Automatic model indexing on create, update, and delete
- **📱 Beautiful UI**: Ready-to-use Alpine.js search component with Tailwind CSS
- **🛠️ Developer Friendly**: Comprehensive console commands and diagnostics
- **🔧 Highly Configurable**: Extensive configuration options for all use cases
- **📊 Queue Support**: Background job processing for large datasets
- **🔄 Soft Delete Support**: Automatic handling of soft-deleted models
- **🎨 Custom Transformers**: Transform model data before indexing

## 📦 Installation

### 1. Install the Package

```bash
composer require laravel-global-search/global-search
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=global-search-config
```

### 3. Configure Environment Variables

Add these to your `.env` file:

```env
# Meilisearch Configuration
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-master-key-here
MEILISEARCH_TIMEOUT=5

# Global Search Configuration
GLOBAL_SEARCH_CACHE_ENABLED=true
GLOBAL_SEARCH_CACHE_STORE=redis
GLOBAL_SEARCH_CACHE_TTL=60
GLOBAL_SEARCH_QUEUE=default
GLOBAL_SEARCH_BATCH_SIZE=1000
```

### 4. Make Your Models Searchable

Add the `Searchable` trait to your models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;

class Product extends Model
{
    use Searchable;
    
    // Your model code...
}
```

### 5. Configure Mappings

Edit `config/global-search.php` to define your model mappings:

```php
'mappings' => [
    [
        'model' => App\Models\Product::class,
        'index' => 'products',
        'primary_key' => 'id',
        'fields' => ['id', 'title', 'description', 'price', 'category'],
        'computed' => [
            'url' => fn($model) => route('products.show', $model->slug),
            'formatted_price' => fn($model) => '$' . number_format($model->price, 2),
        ],
        'filterable' => ['category', 'price'],
        'sortable' => ['price', 'created_at'],
    ],
],
```

### 6. Sync Settings and Index Data

```bash
# Sync Meilisearch index settings
php artisan search:sync-settings

# Index your existing data
php artisan search:reindex
```

## 🚀 Quick Start

### Basic Search

```php
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;

$searchService = app(GlobalSearchService::class);

$results = $searchService->search('laptop', [], 10);
```

### Search with Filters

```php
$results = $searchService->search('laptop', [
    'products' => ['category = electronics', 'price > 500'],
    'users' => ['status = active']
], 20);
```

### API Endpoint

The package automatically registers an API endpoint:

```bash
GET /api/global-search?q=search+query&limit=10
```

### Frontend Component

Include the search component in your Blade templates:

```blade
<x-global-search />
```

## 🎨 Frontend Integration

### Using the Blade Component

The package includes a beautiful, ready-to-use search component:

```blade
<!-- In your layout or any Blade template -->
<x-global-search />
```

### Custom Styling

The component uses Tailwind CSS classes. You can customize the appearance by:

1. Publishing the views:
```bash
php artisan vendor:publish --tag=global-search-views
```

2. Modifying the component template in `resources/views/vendor/global-search/components/global-search.blade.php`

### JavaScript Integration

The component uses Alpine.js. Make sure you have Alpine.js loaded in your application:

```html
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

## ⚙️ Configuration

### Federation Settings

Configure which indexes to search and their relative weights:

```php
'federation' => [
    'indexes' => [
        'products' => ['weight' => 3.0],  // Higher weight = higher priority
        'pages' => ['weight' => 1.0],
        'users' => ['weight' => 2.0],
    ],
    'default_limit' => 10,
    'max_limit' => 50,
],
```

### Cache Configuration

```php
'cache' => [
    'enabled' => true,
    'store' => 'redis',
    'ttl' => 60, // minutes
    'version_key_prefix' => 'gs:index:',
],
```

### Pipeline Settings

```php
'pipeline' => [
    'queue' => 'default',
    'batch_size' => 1000,
    'retry_attempts' => 3,
    'retry_delay' => 60, // seconds
],
```

## 🛠️ Console Commands

### Reindex Data

```bash
# Reindex all models
php artisan search:reindex

# Reindex specific index
php artisan search:reindex products

# Reindex specific IDs
php artisan search:reindex products --ids=1,2,3,4,5

# Reindex with custom chunk size
php artisan search:reindex --chunk=500
```

### Flush Index

```bash
# Clear all documents from an index
php artisan search:flush products

# Skip confirmation prompt
php artisan search:flush products --confirm
```

### Sync Settings

```bash
# Sync all index settings
php artisan search:sync-settings

# Sync specific index
php artisan search:sync-settings --index=products
```

### Warm Cache

```bash
# Warm cache with specific queries
php artisan search:warm-cache --queries="laptop" --queries="phone" --limit=20

# Warm cache from file
php artisan search:warm-cache --file=queries.txt
```

### Diagnostics

```bash
# Run comprehensive diagnostics
php artisan search:doctor

# Show detailed information
php artisan search:doctor --verbose
```

## 🔧 Advanced Usage

### Custom Document Transformers

Create custom transformers for complex data processing:

```php
<?php

namespace App\Search\Transformers;

use LaravelGlobalSearch\GlobalSearch\Contracts\SearchDocumentTransformer;
use Illuminate\Database\Eloquent\Model;

class ProductTransformer implements SearchDocumentTransformer
{
    public function __invoke(Model $model, array $mapping): array
    {
        return [
            'id' => $model->id,
            'title' => $model->title,
            'description' => $model->description,
            'price' => $model->price,
            'category' => $model->category->name,
            'tags' => $model->tags->pluck('name')->toArray(),
            'url' => route('products.show', $model->slug),
            'image' => $model->getFirstMediaUrl('images'),
            'rating' => $model->reviews()->avg('rating'),
        ];
    }
}
```

Then use it in your mapping:

```php
[
    'model' => App\Models\Product::class,
    'index' => 'products',
    'transformer' => App\Search\Transformers\ProductTransformer::class,
],
```

### Custom Link Resolvers

Create link resolvers for generating related links:

```php
<?php

namespace App\Search\Resolvers;

use LaravelGlobalSearch\GlobalSearch\Contracts\SearchResultLinkResolver;

class ProductLinkResolver implements SearchResultLinkResolver
{
    public function resolve(array $hit): array
    {
        return [
            ['label' => 'View Product', 'href' => $hit['url']],
            ['label' => 'Add to Cart', 'href' => route('cart.add', $hit['id'])],
            ['label' => 'Add to Wishlist', 'href' => route('wishlist.add', $hit['id'])],
        ];
    }
}
```

### Manual Indexing

```php
// Index a specific model
$product = Product::find(1);
$product->searchable();

// Remove from search index
$product->unsearchable();

// Index multiple models
Product::where('status', 'published')->searchable();
```

## 📊 Performance Optimization

### Caching Strategy

- Use Redis for optimal cache performance
- Adjust TTL based on your data update frequency
- Monitor cache hit rates

### Queue Configuration

- Use dedicated queues for search indexing
- Adjust batch sizes based on your server capacity
- Monitor queue performance

### Index Settings

- Configure appropriate searchable attributes
- Use typo tolerance for better user experience
- Set up synonyms for common terms

## 🧪 Testing

```bash
# Run diagnostics
php artisan search:doctor

# Test search functionality
php artisan tinker
>>> app(\LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService::class)->search('test')
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security related issues, please email security@laravel-global-search.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Laravel Global Search Team](https://github.com/laravel-global-search)
- [Meilisearch](https://www.meilisearch.com/) for the amazing search engine
- [Laravel](https://laravel.com/) for the excellent framework
- All Contributors

## 📞 Support

- [Documentation](https://laravel-global-search.com/docs)
- [GitHub Issues](https://github.com/laravel-global-search/global-search/issues)
- [Discord Community](https://discord.gg/laravel-global-search)
