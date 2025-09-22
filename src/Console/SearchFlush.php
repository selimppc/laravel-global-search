<?php

namespace Selimppc\GlobalSearch\Console;

use Illuminate\Console\Command;
use Selimppc\GlobalSearch\Support\IndexManager;

class SearchFlush extends Command
{
    protected $signature = 'search:flush {index}';
    protected $description = 'Delete all documents from an index';

    public function handle(IndexManager $indexer): int
    {
        $index = (string)$this->argument('index');
        $indexer->flush($index);
        $this->info("Flushed {$index}");
        return self::SUCCESS;
    }
}