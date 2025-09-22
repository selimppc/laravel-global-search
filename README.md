# selimppc/laravel-global-search

## Install

```
$ composer require selimppc/laravel-global-search
$ php artisan vendor:publish --tag=global-search-config
```


2. Add observers to your models:
```
// app/Models/Product.php
use Selimppc\GlobalSearch\Traits\Searchable;
class Product extends Model { use Searchable; }
```

3. Configure config/global-search.php mappings, settings, and federation.

Then:
```
$ php artisan search:sync-settings
$ php artisan search:reindex # or search:reindex products
```

Query via API:
```
GET /api/global-search?q=seiko+solar
```

Or in PHP:
```
$results = app(\Selimppc\GlobalSearch\Services\FederatedSearch::class)->search('seiko', [
'products' => ['status = published']
], 12);
```

---

## Usage notes

- Ensure Meilisearch is reachable and key is configured.
- Use Redis cache for best performance.
- Bump index weights in `federation.indexes` to prioritize certain indexes.
- Provide your own `transformer` to denormalize EAV attributes.
- Provide `LinkResolver` classes in your app to generate per‑hit related links if you want to display them in your UI layer.
