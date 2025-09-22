<?php

namespace Selimppc\GlobalSearch\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr as LArr;
use Selimppc\GlobalSearch\Contracts\ToSearchDocument;

class IndexManager
{
    public function __construct(private MeiliClient $meili, private array $config)
    {
    }

    public function mappings(): array { return $this->config['mappings'] ?? []; }

    public function findMappingByModel(string $modelClass): ?array
    {
        foreach ($this->mappings() as $m) {
            if (($m['model'] ?? null) === $modelClass) return $m;
        }
        return null;
    }
    public function buildDocument(Model $model, array $mapping): array
    {
        $doc = [];
        foreach (($mapping['fields'] ?? []) as $f) {
            $doc[$f] = data_get($model, $f);
        }
        foreach (($mapping['computed'] ?? []) as $key => $fn) {
            $doc[$key] = $fn($model);
        }
        $pk = $mapping['primary_key'] ?? $model->getKeyName();
        $doc[$pk] = $model->getKey();
        return $doc;
    }

    public function transformer(array $mapping): ?ToSearchDocument
    {
        $class = $mapping['transformer'] ?? null;
        return $class ? app($class) : null;
    }

    public function indexModels(string $modelClass, array $ids): void
    {
        $mapping = $this->findMappingByModel($modelClass);
        if (!$mapping) return;

        $model = new $modelClass; /** @var Model $model */
        $pk = $mapping['primary_key'] ?? $model->getKeyName();

        $query = $modelClass::query()->whereIn($pk, $ids);
        $batch = [];
        $transformer = $this->transformer($mapping);

        foreach ($query->cursor() as $m) {
            $doc = $transformer ? ($transformer)($m, $mapping) : $this->buildDocument($m, $mapping);
            $batch[] = $doc;

            if (count($batch) >= (int)($this->config['pipeline']['batch_size'] ?? 1000)) {
                $this->meili->addDocuments($mapping['index'], $batch, $mapping['primary_key'] ?? null);
            $batch = [];
            }
        }
        if ($batch) {
            $this->meili->addDocuments($mapping['index'], $batch, $mapping['primary_key'] ?? null);
        }

        $this->bumpVersion($mapping['index']);
    }



    public function deleteModels(string $modelClass, array $ids): void
    {
        $mapping = $this->findMappingByModel($modelClass);
        if (!$mapping) return;
        $this->meili->deleteDocuments($mapping['index'], $ids);
        $this->bumpVersion($mapping['index']);
    }

    public function flush(string $index): void
    {
        $this->meili->deleteAllDocuments($index);
        $this->bumpVersion($index);
    }

    public function syncSettings(): void
    {
        foreach ($this->config['index_settings'] ?? [] as $index => $settings) {
            $this->meili->updateSettings($index, $settings);
        }
    }

    public function bumpVersion(string $index): void
    {
        $store = $this->config['cache']['store'] ?? null;
        $prefix = $this->config['cache']['version_key_prefix'] ?? 'ms:index:';
        cache()->store($store)->increment($prefix.$index);
    }

}