<?php

namespace Selimppc\GlobalSearch\Console;

use Illuminate\Console\Command;
use Selimppc\GlobalSearch\Support\IndexManager;

class SearchSyncSettings extends Command
{
    protected $signature = 'search:sync-settings';
    protected $description = 'Push index settings from config to Meilisearch';

    public function handle(IndexManager $indexer): int
    {
        $indexer->syncSettings();
        $this->info('Index settings synced.');
        return self::SUCCESS;
    }
}