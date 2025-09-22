<?php

namespace LaravelGlobalSearch\GlobalSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Job for deleting multiple models from their search indexes.
 */
class DeleteModelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $modelPayload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SearchIndexManager $indexManager): void
    {
        foreach ($this->modelPayload as $modelClass => $modelIds) {
            try {
                $indexManager->deleteModels($modelClass, array_values($modelIds));
                
                Log::info('Successfully deleted models from search index', [
                    'model_class' => $modelClass,
                    'model_count' => count($modelIds)
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to delete models from search index', [
                    'model_class' => $modelClass,
                    'model_ids' => $modelIds,
                    'error' => $e->getMessage()
                ]);
                
                // Re-throw to trigger job retry
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DeleteModelsJob failed permanently', [
            'model_payload' => $this->modelPayload,
            'error' => $exception->getMessage()
        ]);
    }
}