<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use LaravelGlobalSearch\GlobalSearch\Contracts\DataTransformer;

/**
 * Manages data transformers for different model types.
 * Provides a centralized way to handle complex data transformations.
 */
class DataTransformerManager
{
    private array $transformers = [];
    private array $defaultConfig = [];

    public function __construct(array $config)
    {
        $this->defaultConfig = $config;
        $this->registerDefaultTransformers();
    }

    public function getTransformer(string $modelClass): DataTransformer
    {
        if (isset($this->transformers[$modelClass])) {
            return $this->transformers[$modelClass];
        }

        // Create default transformer
        return $this->createDefaultTransformer($modelClass);
    }

    public function registerTransformer(string $modelClass, DataTransformer $transformer): void
    {
        $this->transformers[$modelClass] = $transformer;
    }

    public function transform($model, ?string $tenant = null): array
    {
        $modelClass = get_class($model);
        $transformer = $this->getTransformer($modelClass);
        
        return $transformer->transform($model, $tenant);
    }

    private function registerDefaultTransformers(): void
    {
        // Register transformers from config
        foreach ($this->defaultConfig['mappings'] ?? [] as $mapping) {
            $modelClass = $mapping['model'];
            $transformer = $this->createDefaultTransformer($modelClass, $mapping);
            $this->transformers[$modelClass] = $transformer;
        }
    }

    private function createDefaultTransformer(string $modelClass, array $config = []): DataTransformer
    {
        $mergedConfig = array_merge($this->defaultConfig, $config);
        return new DefaultDataTransformer($modelClass, $mergedConfig);
    }
}
