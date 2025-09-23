# Multi-Tenancy Support

This Laravel Global Search package now supports both single-database and multi-tenant database scenarios. The multi-tenancy implementation provides complete tenant isolation while maintaining backward compatibility with existing single-tenant setups.

## Features

- **Tenant Isolation**: Each tenant has completely isolated search indexes
- **Flexible Tenant Resolution**: Support for subdomain, header, route parameter, and custom tenant detection
- **Backward Compatibility**: Existing single-tenant setups continue to work without changes
- **Automatic Tenant Context**: Models automatically detect and use the correct tenant context
- **Tenant-Specific Commands**: Dedicated Artisan commands for tenant operations
- **Configurable Index Naming**: Customizable tenant index naming patterns

## Configuration

### Enable Multi-Tenancy

Set the environment variable to enable multi-tenancy:

```bash
GLOBAL_SEARCH_MULTI_TENANT=true
```

### Tenant Resolution Strategies

The package supports multiple tenant resolution strategies, checked in order:

#### 1. Subdomain-based Resolution

```php
// config/global-search.php
'tenant' => [
    'enabled' => true,
    'strategies' => [
        [
            'type' => 'subdomain',
            'pattern' => '^([^.]+)\.',
            'exclude' => ['www', 'api', 'admin', 'app'],
        ],
    ],
],
```

This will resolve tenants from subdomains like:
- `tenant1.example.com` → tenant: `tenant1`
- `company.example.com` → tenant: `company`

#### 2. Header-based Resolution

```php
'strategies' => [
    [
        'type' => 'header',
        'header' => 'X-Tenant-ID',
    ],
],
```

This will resolve tenants from HTTP headers:
```bash
curl -H "X-Tenant-ID: tenant1" /api/search
```

#### 3. Route Parameter Resolution

```php
'strategies' => [
    [
        'type' => 'route',
        'parameter' => 'tenant',
    ],
],
```

This will resolve tenants from route parameters:
```php
Route::get('/{tenant}/search', GlobalSearchController::class);
```

#### 4. Custom Resolution

```php
'strategies' => [
    [
        'type' => 'custom',
        'resolver' => function() {
            return auth()->user()?->tenant_id;
        },
    ],
],
```

### Tenant Source Configuration

Configure how the package discovers all available tenants:

#### Database Source

```php
'tenant' => [
    'source' => 'database',
    'model' => 'App\\Models\\Tenant',
    'identifier_column' => 'id',
],
```

#### Configuration Source

```php
'tenant' => [
    'source' => 'config',
    'list' => ['tenant1', 'tenant2', 'tenant3'],
],
```

#### Custom Source

```php
'tenant' => [
    'source' => 'custom',
    'list_resolver' => function() {
        return Tenant::pluck('slug')->toArray();
    },
],
```

## Model Configuration

### Basic Multi-Tenant Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;

class Product extends Model
{
    use Searchable;
    
    protected $fillable = [
        'title', 'description', 'price', 'tenant_id'
    ];
    
    // The Searchable trait will automatically detect tenant_id
    // and use it for tenant-specific indexing
}
```

### Custom Tenant Resolution

If your model uses a different tenant field or method:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;

class Product extends Model
{
    use Searchable;
    
    protected $fillable = [
        'title', 'description', 'price', 'company_id'
    ];
    
    /**
     * Override the default tenant resolution
     */
    public function getTenantId(): ?string
    {
        return $this->company_id;
    }
}
```

## Usage Examples

### Single Database (Default)

For single-database applications, no changes are required. The package works exactly as before:

```php
// Search across all data
$results = app(GlobalSearchService::class)->search('laptop');

// Index a model
$product = Product::create(['title' => 'MacBook Pro']);
$product->searchable(); // Automatically indexed
```

### Multi-Tenant Database

For multi-tenant applications, the package automatically handles tenant isolation:

```php
// Search within current tenant context
$results = app(GlobalSearchService::class)->search('laptop');

// Search for specific tenant
$results = app(GlobalSearchService::class)->search('laptop', [], 10, 'tenant1');

// Index models (automatically uses tenant context)
$product = Product::create([
    'title' => 'MacBook Pro',
    'tenant_id' => 'tenant1'
]);
$product->searchable(); // Indexed in tenant1's index
```

### API Usage

#### Single Tenant

```bash
curl "https://api.example.com/search?q=laptop"
```

#### Multi-Tenant

```bash
# Subdomain-based
curl "https://tenant1.example.com/search?q=laptop"

# Header-based
curl -H "X-Tenant-ID: tenant1" "https://api.example.com/search?q=laptop"

# Route parameter
curl "https://api.example.com/tenant1/search?q=laptop"

# Explicit tenant parameter
curl "https://api.example.com/search?q=laptop&tenant=tenant1"
```

## Artisan Commands

### Tenant-Specific Commands

```bash
# List all tenants
php artisan global-search:list-tenants

# Reindex all models for a specific tenant
php artisan global-search:reindex-tenant tenant1

# Reindex specific model for a tenant
php artisan global-search:reindex-tenant tenant1 --model="App\\Models\\Product"

# Flush all indexes for a tenant
php artisan global-search:flush-tenant tenant1

# Flush specific index for a tenant
php artisan global-search:flush-tenant tenant1 --index=products
```

### Global Commands (All Tenants)

```bash
# Reindex all models for all tenants
php artisan global-search:reindex

# Flush all indexes for all tenants
php artisan global-search:flush

# Sync settings for all tenants
php artisan global-search:sync-settings
```

## Index Naming

In multi-tenant mode, indexes are automatically prefixed with tenant identifiers:

- Single tenant: `products`
- Multi-tenant: `products_tenant_tenant1`, `products_tenant_tenant2`

The separator can be configured:

```php
'tenant' => [
    'index_separator' => '_', // Default
],
```

## Caching

Search results are cached per tenant to ensure complete isolation:

- Single tenant: `gs:single:...`
- Multi-tenant: `gs:abc123:...` (where abc123 is the tenant hash)

## Best Practices

### 1. Tenant Model Setup

Create a dedicated tenant model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'status'];
    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
```

### 2. Middleware for Tenant Resolution

Create middleware to set tenant context:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);
        
        if ($tenant) {
            // Set tenant in request for easy access
            $request->merge(['tenant' => $tenant]);
        }
        
        return $next($request);
    }
    
    private function resolveTenant(Request $request): ?string
    {
        // Your custom tenant resolution logic
        return $request->route('tenant') ?? $request->header('X-Tenant-ID');
    }
}
```

### 3. Database Migrations

Ensure your models have tenant identification:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description');
    $table->decimal('price', 10, 2);
    $table->string('tenant_id'); // Add tenant identification
    $table->timestamps();
    
    $table->index(['tenant_id', 'created_at']);
});
```

### 4. Model Scoping

Add tenant scoping to your models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;

class Product extends Model
{
    use Searchable;
    
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            $tenant = app(TenantResolver::class)->getCurrentTenant();
            if ($tenant) {
                $query->where('tenant_id', $tenant);
            }
        });
    }
}
```

## Troubleshooting

### Common Issues

1. **Tenant not found**: Ensure your tenant resolution strategies are correctly configured
2. **Models not indexing**: Check that models have tenant identification (tenant_id field or getTenantId method)
3. **Search returning wrong results**: Verify tenant context is being set correctly
4. **Index not found**: Ensure tenant-specific indexes are created (run reindex commands)

### Debug Commands

```bash
# Check tenant resolution
php artisan tinker
>>> app(TenantResolver::class)->getCurrentTenant()

# List all tenants
php artisan global-search:list-tenants

# Check index status
php artisan global-search:doctor
```

## Migration from Single to Multi-Tenant

1. **Enable multi-tenancy** in configuration
2. **Add tenant identification** to your models
3. **Configure tenant resolution** strategies
4. **Run tenant-specific reindex** commands
5. **Update your application** to handle tenant context

The package maintains full backward compatibility, so you can enable multi-tenancy gradually.
