<?php

namespace LaravelGlobalSearch\GlobalSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;

/**
 * Modern, efficient deletion job for scale.
 */
class DeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $modelClass,
        private array $modelIds,
        private ?string $tenant = null
    ) {
        $this->onQueue('search');
    }

    public function handle(TenantResolver $tenantResolver): void
    {
        try {
            $tenant = $this->tenant ?? $tenantResolver->getCurrentTenant();
            $client = App::make(Client::class);
            
            // Get index name
            $indexName = $this->getIndexName($tenant);
            
            // Delete documents
            $index = $client->index($indexName);
            $index->deleteDocuments($this->modelIds);
            
        } catch (\Exception $e) {
            Log::error('Deletion job failed', [
                'model' => $this->modelClass,
                'tenant' => $this->tenant,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function getIndexName(?string $tenant): string
    {
        $baseIndex = strtolower(class_basename($this->modelClass));
        return $tenant ? "{$baseIndex}_{$tenant}" : $baseIndex;
    }
}
