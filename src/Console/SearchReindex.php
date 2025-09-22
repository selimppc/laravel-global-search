<?php

namespace Selimppc\GlobalSearch\Console;

use Illuminate\Console\Command;
use Selimppc\GlobalSearch\Support\IndexManager;

class SearchReindex extends Command
{
    protected $signature = 'search:reindex {index?} {--ids=} {--chunk=1000}';
    protected $description = 'Reindex one or all indexes';

    public function handle(IndexManager $indexer): int
    {
        $index = $this->argument('index');
        $idsOpt = $this->option('ids');
        $ids = $idsOpt ? array_filter(explode(',', $idsOpt)) : null;

        $mappings = $index ? array_filter($indexer->mappings(), fn($m) => $m['index'] === $index) : $indexer->mappings();
        foreach ($mappings as $m) {
        $modelClass = $m['model'];
        $model = new $modelClass; $pk = $m['primary_key'] ?? $model->getKeyName();

        $query = $modelClass::query();
        if ($ids) $query->whereIn($pk, $ids);

        $count = $query->count();
        $this->info("Reindexing {$m['index']} ({$count} records)…");

        $query->orderBy($pk)->chunk((int)$this->option('chunk'), function ($chunk) use ($indexer, $modelClass) {
        $ids = $chunk->pluck($chunk->first()->getKeyName())->all();
        $indexer->indexModels($modelClass, $ids);
        });
        }
        $this->info('Done');
        return self::SUCCESS;
    }
}