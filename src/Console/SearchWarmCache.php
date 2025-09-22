<?php

namespace Selimppc\GlobalSearch\Console;

use Illuminate\Console\Command;
use Selimppc\GlobalSearch\Services\FederatedSearch;

class SearchWarmCache extends Command
{
    protected $signature = 'search:warm-cache {--q=*} {--limit=10}';
    protected $description = 'Warm federated search cache for given queries';

    public function handle(FederatedSearch $svc): int
    {
        $queries = (array) $this->option('q');
        $limit = (int) $this->option('limit');
        foreach ($queries as $q) {
        $this->info("Warming: {$q}");
        $svc->search($q, [], $limit);
        }
        $this->info('Done');
        return self::SUCCESS;
    }
}