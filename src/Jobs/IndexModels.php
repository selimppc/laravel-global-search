<?php

namespace Selimppc\GlobalSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Selimppc\GlobalSearch\Support\IndexManager;

class IndexModels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $payload) {}

    public function handle(IndexManager $indexer): void
    {
        foreach ($this->payload as $modelClass => $ids) {
            $indexer->indexModels($modelClass, array_values($ids));
        }
    }
}