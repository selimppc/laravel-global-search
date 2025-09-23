<?php

namespace App\Transformers;

use LaravelGlobalSearch\GlobalSearch\Contracts\DataTransformer;

/**
 * Example: Custom Product Data Transformer
 * Shows how to handle complex product data with relationships.
 */
class ProductDataTransformer implements DataTransformer
{
    public function transform($model, ?string $tenant = null): array
    {
        $data = [
            'id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
            'description' => $this->cleanDescription($model->description),
            'short_description' => $this->truncateText($model->description, 150),
            'price' => $this->formatPrice($model->price),
            'sale_price' => $this->formatPrice($model->sale_price),
            'sku' => $model->sku,
            'stock_quantity' => $model->stock_quantity,
            'is_in_stock' => $model->stock_quantity > 0,
            'status' => $this->getStatusText($model->status),
            'featured' => (bool) $model->featured,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];

        // Add tenant context
        if ($tenant) {
            $data['tenant_id'] = $tenant;
        }

        // Add images
        if ($model->relationLoaded('images')) {
            $data['images'] = $model->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => asset('storage/' . $image->path),
                    'alt' => $image->alt_text,
                    'is_primary' => $image->is_primary,
                ];
            })->toArray();
            
            $data['primary_image'] = $data['images'][0]['url'] ?? null;
        }

        // Add categories
        if ($model->relationLoaded('categories')) {
            $data['categories'] = $model->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'path' => $this->buildCategoryPath($category),
                ];
            })->toArray();
        }

        // Add tags
        if ($model->relationLoaded('tags')) {
            $data['tags'] = $model->tags->pluck('name')->toArray();
        }

        // Add attributes
        if ($model->relationLoaded('attributes')) {
            $data['attributes'] = $model->attributes->mapWithKeys(function ($attribute) {
                return [$attribute->name => $attribute->value];
            })->toArray();
        }

        // Add computed fields
        $data['discount_percentage'] = $this->calculateDiscount($model);
        $data['search_text'] = $this->buildSearchText($model);
        $data['url'] = route('products.show', $model->slug);

        // Add metadata
        $data['_search_metadata'] = [
            'model_type' => 'Product',
            'model_class' => get_class($model),
            'indexed_at' => now()->toISOString(),
            'url' => route('products.show', $model->slug),
        ];

        return $data;
    }

    public function getModelClass(): string
    {
        return \App\Models\Product::class;
    }

    public function getSearchableFields(): array
    {
        return [
            'name', 'description', 'short_description', 'sku', 
            'search_text', 'categories.name', 'tags'
        ];
    }

    public function getFilterableFields(): array
    {
        return [
            'status', 'featured', 'is_in_stock', 'price', 'sale_price',
            'categories.id', 'categories.name', 'created_at'
        ];
    }

    public function getSortableFields(): array
    {
        return [
            'name', 'price', 'sale_price', 'created_at', 'updated_at'
        ];
    }

    private function cleanDescription(?string $description): ?string
    {
        if (!$description) return null;
        
        // Remove HTML tags and extra whitespace
        $cleaned = strip_tags($description);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return trim($cleaned);
    }

    private function truncateText(?string $text, int $length): ?string
    {
        if (!$text) return null;
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . '...';
    }

    private function formatPrice(?float $price): ?string
    {
        if (!$price) return null;
        
        return number_format($price, 2);
    }

    private function getStatusText(int $status): string
    {
        return match($status) {
            0 => 'Draft',
            1 => 'Active',
            2 => 'Inactive',
            3 => 'Archived',
            default => 'Unknown'
        };
    }

    private function buildCategoryPath($category): string
    {
        $path = [$category->name];
        
        $parent = $category->parent;
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $path);
    }

    private function calculateDiscount($model): ?float
    {
        if (!$model->sale_price || !$model->price) {
            return null;
        }
        
        $discount = (($model->price - $model->sale_price) / $model->price) * 100;
        return round($discount, 2);
    }

    private function buildSearchText($model): string
    {
        $parts = [
            $model->name,
            $model->description,
            $model->sku,
        ];

        if ($model->relationLoaded('categories')) {
            $parts = array_merge($parts, $model->categories->pluck('name')->toArray());
        }

        if ($model->relationLoaded('tags')) {
            $parts = array_merge($parts, $model->tags->pluck('name')->toArray());
        }

        if ($model->relationLoaded('attributes')) {
            $parts = array_merge($parts, $model->attributes->pluck('value')->toArray());
        }

        return implode(' ', array_filter($parts));
    }
}
