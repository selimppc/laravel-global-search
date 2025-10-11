# Pagination with Offset - Examples

## ✅ Added in v1.1.37

The package now supports **pagination with offset**, allowing you to fetch results in pages.

---

## 📖 How It Works

### Response Meta:
```json
{
  "meta": {
    "total": 150,      // Total results across all indexes
    "count": 20,       // Number of results in this page
    "offset": 40,      // Current offset
    "limit": 20,       // Results per page
    "query": "admin",
    "tenant": "01h4n2h39042mwwm47xzprk1ac"
  }
}
```

---

## 🔥 Usage Examples

### 1. Basic Pagination

```http
# Page 1 (first 20 results)
GET /global-search?q=admin&limit=20&offset=0

# Page 2 (results 21-40)
GET /global-search?q=admin&limit=20&offset=20

# Page 3 (results 41-60)
GET /global-search?q=admin&limit=20&offset=40
```

### 2. With Filters

```http
# Page 1 with filters
GET /global-search?q=user&filters[user_type]=Client&limit=10&offset=0

# Page 2 with filters
GET /global-search?q=user&filters[user_type]=Client&limit=10&offset=10
```

### 3. With Sorting

```http
# Page 1 sorted by name
GET /global-search?q=product&sort[name]=asc&limit=25&offset=0

# Page 2 sorted by name
GET /global-search?q=product&sort[name]=asc&limit=25&offset=25
```

### 4. Full Example (Filter + Sort + Pagination)

```http
GET /global-search?tenant=01h4n2h39042mwwm47xzprk1ac&q=property&filters[status]=active&filters[price]=>100000&sort[price]=asc&limit=50&offset=100
```

---

## 💡 Calculating Pagination

### JavaScript Example:

```javascript
const perPage = 20;
const currentPage = 3;
const offset = (currentPage - 1) * perPage;

// For page 3 with 20 items per page:
// offset = (3 - 1) * 20 = 40

const url = `/global-search?q=${query}&limit=${perPage}&offset=${offset}`;
```

### PHP Example:

```php
$perPage = 20;
$currentPage = request('page', 1);
$offset = ($currentPage - 1) * $perPage;

$results = app('global-search')->search(
    query: 'admin',
    filters: ['status' => 'active'],
    limit: $perPage,
    tenant: '01h4n2h39042mwwm47xzprk1ac',
    sort: ['name' => 'asc'],
    offset: $offset
);

// Calculate total pages
$totalPages = ceil($results['meta']['total'] / $perPage);
```

### Laravel Pagination Helper:

```php
use Illuminate\Pagination\LengthAwarePaginator;

$results = app('global-search')->search('admin', [], 20, null, [], 40);

$paginator = new LengthAwarePaginator(
    items: $results['hits'],
    total: $results['meta']['total'],
    perPage: $results['meta']['limit'],
    currentPage: ($results['meta']['offset'] / $results['meta']['limit']) + 1,
    options: ['path' => request()->url(), 'query' => request()->query()]
);

return response()->json([
    'data' => $paginator->items(),
    'pagination' => [
        'total' => $paginator->total(),
        'per_page' => $paginator->perPage(),
        'current_page' => $paginator->currentPage(),
        'last_page' => $paginator->lastPage(),
        'from' => $paginator->firstItem(),
        'to' => $paginator->lastItem(),
    ]
]);
```

---

## 🎯 Response Structure

### With offset=0, limit=2:
```json
{
  "success": true,
  "data": {
    "hits": [
      { "id": "1", "name": "Admin User 1" },
      { "id": "2", "name": "Admin User 2" }
    ],
    "meta": {
      "total": 150,
      "count": 2,
      "offset": 0,
      "limit": 2,
      "indexes": ["users", "properties"],
      "query": "admin",
      "tenant": "01h4n2h39042mwwm47xzprk1ac",
      "duration_ms": 45.23
    }
  },
  "meta": {
    "query": "admin",
    "limit": 2,
    "offset": 0,
    "tenant": "01h4n2h39042mwwm47xzprk1ac",
    "sort": []
  }
}
```

### With offset=2, limit=2:
```json
{
  "success": true,
  "data": {
    "hits": [
      { "id": "3", "name": "Admin User 3" },
      { "id": "4", "name": "Admin User 4" }
    ],
    "meta": {
      "total": 150,
      "count": 2,
      "offset": 2,
      "limit": 2
    }
  }
}
```

---

## ⚡ Performance Tips

1. **Use reasonable page sizes**: 10-50 items per page is ideal
2. **Cache is offset-aware**: Each offset+limit combination is cached separately
3. **Meilisearch handles offset natively**: Efficient at database level
4. **Monitor slow queries**: Large offsets (1000+) may be slower

---

## 🔧 Default Values

- **offset**: `0` (if not provided)
- **limit**: From `GLOBAL_SEARCH_DEFAULT_LIMIT` (default: `10`)
- **max limit**: From `GLOBAL_SEARCH_MAX_LIMIT` (default: `100`)

---

## 🚀 Quick Start

```bash
# Ensure you're on v1.1.37+
composer require "laravel-global-search/global-search:^1.1.37"

# Test pagination
curl "http://localhost:8000/global-search?q=test&limit=5&offset=0"
curl "http://localhost:8000/global-search?q=test&limit=5&offset=5"
curl "http://localhost:8000/global-search?q=test&limit=5&offset=10"
```

---

## 📚 See Also

- [README.md](README.md) - Main documentation
- [CONFIGURATION.md](CONFIGURATION.md) - Full configuration guide
- [CHANGELOG.md](CHANGELOG.md) - Version history

