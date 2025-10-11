# Laravel Global Search

A powerful, fully configurable Laravel package for global search with Meilisearch integration and multi-tenancy support.

> **v1.1.29**: 🚀 **Fully Dynamic & Configurable** - Zero hardcoded values! Every aspect is user-configurable via environment variables.

## 📖 Documentation

- **[Configuration Guide](CONFIGURATION.md)** - Complete reference for all 30+ configuration options
- **[Changelog](CHANGELOG.md)** - Version history and upgrade guides

---

## 🚀 Quick Start

### 1. Install

```bash
composer require laravel-global-search/global-search
```

### 2. Configure

```bash
# Publish config
php artisan vendor:publish --tag=global-search-config

# Add to .env
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key
```

### 3. Setup Models

Edit `config/global-search.php`:

```php
'mappings' => [
    [
        'model' => App\Models\User::class,
        'index' => 'users',
        'primaryKey' => 'id',
        'fields' => ['id', 'name', 'email', 'phone', 'created_at'],
        'filterable' => ['user_type', 'status', 'created_at'],
        'sortable' => ['name', 'created_at', 'updated_at'],
        'computed' => [
            'url' => fn($model) => route('users.show', $model->id),
        ],
    ],
],
```

### 4. Sync Settings & Index

```bash
# Step 1: Sync settings to Meilisearch (REQUIRED for filters/sorting)
php artisan search:sync-settings

# Step 2: Index your data
php artisan search:reindex

# Step 3: Process the queue
php artisan queue:work --stop-when-empty
```

> **⚠️ Important:** Always run `search:sync-settings` **before** indexing when you:
> - First set up the package
> - Change filterable/sortable attributes in config
> - Add new indexes or modify index settings
>
> **Without syncing settings, filters and sorting will NOT work!**

```php
// Search via API
GET /global-search?q=john&limit=10

// Or use the service
$results = app('global-search')->search('john');
```

---

## 🎯 Core Features

- **100% Configurable** - No hardcoded values, all via config/env
- **Multi-Tenancy** - Automatic tenant detection and isolation
- **Dynamic Limits** - Configurable default/max limits
- **Smart Caching** - Configurable TTL and cache drivers
- **Performance Monitoring** - Slow query detection and metrics
- **Advanced Filtering** - Filter by any configured field
- **Sorting** - Multi-field sorting support
- **Job-Based** - All operations use background jobs

---

## 📊 API Usage

### Basic Search

```http
GET /global-search?q=john
```

### With Filters

```http
GET /global-search?q=john&filters[user_type]=Client&filters[status]=1
```

### With Sorting

```http
GET /global-search?q=john&sort[name]=asc&sort[created_at]=desc
```

### With Limit

```http
GET /global-search?q=john&limit=50
```

### Multi-Tenant

```http
# Automatic detection from subdomain
GET https://tenant1.yourdomain.com/global-search?q=john

# Or specify tenant
GET /global-search?q=john&tenant=tenant1
```

---

## 🔧 Configuration

### Essential Settings

```env
# Meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key

# Limits
GLOBAL_SEARCH_DEFAULT_LIMIT=10
GLOBAL_SEARCH_MAX_LIMIT=100

# Cache
GLOBAL_SEARCH_CACHE_ENABLED=true
GLOBAL_SEARCH_CACHE_TTL=60

# Pipeline
GLOBAL_SEARCH_BATCH_SIZE=1000
GLOBAL_SEARCH_CHUNK_SIZE=100

# Multi-Tenancy
GLOBAL_SEARCH_TENANT_ENABLED=true
```

**See [CONFIGURATION.md](CONFIGURATION.md) for complete options.**

---

## 🛠️ Commands

```bash
# Reindex all data
php artisan search:reindex

# Sync Meilisearch settings
php artisan search:sync-settings

# Check health
php artisan search:health

# View performance metrics
php artisan search:performance

# Flush all indexes
php artisan search:flush
```

---

## 🏢 Multi-Tenancy

Enable in config:

```php
'tenant' => [
    'enabled' => true,
    'identifier_column' => 'id',
],
```

Automatic detection from:
- Subdomain: `tenant1.domain.com`
- Header: `X-Tenant-ID: tenant1`
- Query: `?tenant=tenant1`
- Route: `/tenant/{tenant}/...`
- Auth user: `auth()->user()->tenant_id`

---

## 🔍 Filtering & Sorting

### Configure in `config/global-search.php`:

```php
'mappings' => [
    [
        'model' => App\Models\Product::class,
        'index' => 'products',
        'filterable' => ['status', 'category', 'price', 'created_at'],
        'sortable' => ['price', 'name', 'created_at'],
    ],
],

'index_settings' => [
    'products' => [
        'filterableAttributes' => ['status', 'category', 'price', 'created_at'],
        'sortableAttributes' => ['price', 'name', 'created_at'],
        'searchableAttributes' => ['name', 'description', 'sku'],
    ],
],
```

### Apply settings:

```bash
# Step 1: Sync settings first (REQUIRED!)
php artisan search:sync-settings

# Step 2: Reindex data
php artisan search:reindex

# Step 3: Process queue
php artisan queue:work --stop-when-empty
```

> **💡 Tip:** After changing any filterable/sortable attributes in your config, always run `search:sync-settings` first!

### Use in API:

```http
# Filter
GET /global-search?q=product&filters[status]=active&filters[category]=electronics

# Sort
GET /global-search?q=product&sort[price]=asc&sort[created_at]=desc

# Both
GET /global-search?q=product&filters[status]=active&sort[price]=asc&limit=20
```

---

## 🎨 Data Transformation

### 1. Automatic (No code needed)

```php
class User extends Model
{
    // That's it!
}
```

### 2. Model Method

```php
class User extends Model
{
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'full_name' => $this->first_name . ' ' . $this->last_name,
        ];
    }
}
```

### 3. Config-Based

```php
'mappings' => [
    [
        'model' => App\Models\User::class,
        'index' => 'users',
        'fields' => ['id', 'name', 'email'],
        'computed' => [
            'url' => fn($m) => route('users.show', $m->id),
            'full_name' => fn($m) => $m->first_name . ' ' . $m->last_name,
        ],
    ],
],
```

---

## 🔒 Production Security (IMPORTANT!)

**⚠️ The global search endpoint is PUBLIC by default.** Secure it in production!

### Simple Authentication (Recommended)

Add this to your `routes/api.php`:

```php
// Protect the search endpoint with authentication
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/global-search', function(Request $request) {
        // Force user's tenant for security
        $tenant = auth()->user()->tenant_id ?? null;
        
        return app(\LaravelGlobalSearch\GlobalSearch\Http\Controllers\GlobalSearchController::class)(
            $request->merge(['tenant' => $tenant])
        );
    });
});
```

**Usage with token:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.yourdomain.com/global-search?q=search"
```

### Or Disable in Production

```php
// routes/api.php - Only enable in development
if (!app()->environment('production')) {
    Route::get('/global-search', [GlobalSearchController::class, '__invoke']);
}
```

**💡 For more security options, see [CONFIGURATION.md](CONFIGURATION.md)**

---

## ⚡ Performance Tuning

### High-Volume Apps

```env
GLOBAL_SEARCH_BATCH_SIZE=5000
GLOBAL_SEARCH_CHUNK_SIZE=500
GLOBAL_SEARCH_MAX_LIMIT=50
GLOBAL_SEARCH_CACHE_TTL=600
```

### Low-Memory Environments

```env
GLOBAL_SEARCH_BATCH_SIZE=100
GLOBAL_SEARCH_CHUNK_SIZE=10
GLOBAL_SEARCH_MAX_RELATIONSHIP_ITEMS=5
```

### Development

```env
GLOBAL_SEARCH_CACHE_ENABLED=false
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=100
```

---

## 🔧 Troubleshooting

### Issue: No search results

```bash
# Fix everything
php artisan search:reindex
php artisan queue:work --stop-when-empty
```

### Issue: Filters not working

```bash
# Sync settings and reindex
php artisan search:sync-settings
php artisan search:reindex
```

### Issue: Slow queries

```env
# Enable monitoring
GLOBAL_SEARCH_LOG_SLOW_QUERIES=true
GLOBAL_SEARCH_SLOW_QUERY_THRESHOLD=500
```

Check logs for slow query warnings.

---

## 📦 Response Format

```json
{
  "success": true,
  "data": {
    "hits": [
      {
        "id": "01j2zp7zgf",
        "name": "John Doe",
        "email": "john@example.com",
        "user_type": "Client",
        "url": "/users/01j2zp7zgf",
        "_index": "users"
      }
    ],
    "meta": {
      "total": 1,
      "indexes": ["users"],
      "query": "john",
      "limit": 10,
      "tenant": "tenant1",
      "duration_ms": 45.23
    }
  },
  "meta": {
    "query": "john",
    "limit": 10,
    "tenant": "tenant1",
    "sort": []
  }
}
```

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

---

## 🆘 Support

- **Configuration**: See [CONFIGURATION.md](CONFIGURATION.md)
- **Changelog**: See [CHANGELOG.md](CHANGELOG.md)
- **Issues**: [GitHub Issues](https://github.com/laravel-global-search/global-search/issues)

---

**Built with ❤️ for Laravel developers who need powerful, flexible search.**
