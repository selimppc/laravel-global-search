<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use LaravelGlobalSearch\GlobalSearch\Contracts\DataTransformer;

/**
 * Default data transformer with advanced transformation capabilities.
 * Handles complex real-world data transformation scenarios.
 */
class DefaultDataTransformer implements DataTransformer
{
    public function __construct(
        private string $modelClass,
        private array $config
    ) {}

    public function transform($model, ?string $tenant = null): array
    {
        // Start with basic model data
        $data = $this->getBasicModelData($model);
        
        // Apply custom transformations
        $data = $this->applyCustomTransformations($model, $data);
        
        // Add computed fields
        $data = $this->addComputedFields($model, $data);
        
        // Add relationships
        $data = $this->addRelationships($model, $data);
        
        // Add tenant context (configurable)
        if ($tenant && config('global-search.transformation.add_tenant_id', true)) {
            $data['tenant_id'] = $tenant;
        }
        
        // Add metadata
        $data = $this->addMetadata($model, $data);
        
        // Clean and validate data
        return $this->cleanData($data);
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getSearchableFields(): array
    {
        return $this->config['fields'] ?? [];
    }

    public function getFilterableFields(): array
    {
        return $this->config['filterable'] ?? [];
    }

    public function getSortableFields(): array
    {
        return $this->config['sortable'] ?? [];
    }

    private function getBasicModelData($model): array
    {
        // Use toArray() but handle potential issues
        try {
            return $model->toArray();
        } catch (\Exception $e) {
            // Fallback to manual attribute extraction
            return $this->extractAttributesManually($model);
        }
    }

    private function extractAttributesManually($model): array
    {
        $data = [];
        
        // Get fillable attributes
        $fillable = $model->getFillable();
        foreach ($fillable as $attribute) {
            if ($model->offsetExists($attribute)) {
                $data[$attribute] = $model->getAttribute($attribute);
            }
        }
        
        // Add primary key
        $data['id'] = $model->getKey();
        
        return $data;
    }

    private function applyCustomTransformations($model, array $data): array
    {
        // Check if model has custom transformation method
        if (method_exists($model, 'toSearchableArray')) {
            $customData = $model->toSearchableArray();
            $data = array_merge($data, $customData);
        }
        
        // Apply field-specific transformations
        foreach ($this->config['transformations'] ?? [] as $field => $transformation) {
            if (isset($data[$field])) {
                $data[$field] = $this->applyFieldTransformation($data[$field], $transformation);
            }
        }
        
        return $data;
    }

    private function applyFieldTransformation($value, $transformation): mixed
    {
        if (is_callable($transformation)) {
            return $transformation($value);
        }
        
        if (is_string($transformation)) {
            return match($transformation) {
                'date' => $this->transformDate($value),
                'currency' => $this->transformCurrency($value),
                'html' => $this->transformHtml($value),
                'json' => $this->transformJson($value),
                'slug' => $this->transformSlug($value),
                'url' => $this->transformUrl($value),
                'phone' => $this->transformPhone($value),
                'email' => $this->transformEmail($value),
                default => $value
            };
        }
        
        return $value;
    }

    private function addComputedFields($model, array $data): array
    {
        // Add computed fields from config
        foreach ($this->config['computed'] ?? [] as $field => $callback) {
            if (is_callable($callback)) {
                $data[$field] = $callback($model);
            } elseif (is_string($callback) && method_exists($model, $callback)) {
                $data[$field] = $model->{$callback}();
            }
        }
        
        return $data;
    }

    private function addRelationships($model, array $data): array
    {
        // Add relationship data
        foreach ($this->config['relationships'] ?? [] as $relation => $config) {
            if ($model->relationLoaded($relation) || $model->relationExists($relation)) {
                $relationData = $this->transformRelationship($model->{$relation}, $config);
                if ($relationData) {
                    $data[$relation] = $relationData;
                }
            }
        }
        
        return $data;
    }

    private function transformRelationship($relation, array $config): mixed
    {
        if (!$relation) {
            return null;
        }
        
        $fields = $config['fields'] ?? ['id', 'name'];
        // Use global transformation config if not specified per-relationship
        $maxItems = $config['max_items'] ?? config('global-search.transformation.max_relationship_items', 10);
        
        if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
            return $relation->take($maxItems)->map(function ($item) use ($fields) {
                return $item->only($fields);
            })->toArray();
        }
        
        return $relation->only($fields);
    }

    private function addMetadata($model, array $data): array
    {
        // Check if metadata should be added
        if (!config('global-search.transformation.add_metadata', true)) {
            return $data;
        }
        
        // Use computed URL if it exists, otherwise generate one
        $url = $data['url'] ?? $this->generateUrl($model);
        
        // Add search metadata
        $data['_search_metadata'] = [
            'model_type' => class_basename($model),
            'model_class' => get_class($model),
            'indexed_at' => now()->toISOString(),
            'url' => $url,
        ];
        
        return $data;
    }

    private function generateUrl($model): string
    {
        $routeName = strtolower(class_basename($model)) . '.show';
        
        if (app('router')->has($routeName)) {
            return route($routeName, $model->getKey());
        }
        
        return url('/') . '/' . strtolower(class_basename($model)) . '/' . $model->getKey();
    }

    private function cleanData(array $data): array
    {
        $cleanNulls = config('global-search.transformation.clean_null_values', false);
        $cleanEmpty = config('global-search.transformation.clean_empty_strings', false);
        
        // Conditionally remove null values and empty strings
        if ($cleanNulls || $cleanEmpty) {
            $data = array_filter($data, function ($value) use ($cleanNulls, $cleanEmpty) {
                if ($cleanNulls && $value === null) {
                    return false;
                }
                if ($cleanEmpty && $value === '') {
                    return false;
                }
                return true;
            });
        }
        
        // Convert objects to arrays
        array_walk_recursive($data, function (&$value) {
            if (is_object($value)) {
                $value = (array) $value;
            }
        });
        
        return $data;
    }

    // Field transformation methods
    private function transformDate($value): ?string
    {
        if (!$value) return null;
        
        try {
            return \Carbon\Carbon::parse($value)->toISOString();
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function transformCurrency($value): ?string
    {
        if (!$value) return null;
        
        return number_format((float) $value, 2);
    }

    private function transformHtml($value): ?string
    {
        if (!$value) return null;
        
        return strip_tags($value);
    }

    private function transformJson($value): mixed
    {
        if (!$value) return null;
        
        if (is_string($value)) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    private function transformSlug($value): ?string
    {
        if (!$value) return null;
        
        return \Illuminate\Support\Str::slug($value);
    }

    private function transformUrl($value): ?string
    {
        if (!$value) return null;
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return url($value);
    }

    private function transformPhone($value): ?string
    {
        if (!$value) return null;
        
        return preg_replace('/[^0-9+]/', '', $value);
    }

    private function transformEmail($value): ?string
    {
        if (!$value) return null;
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
