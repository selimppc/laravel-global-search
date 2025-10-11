<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Meilisearch\Client;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class FixPrimaryKeysCommand extends Command
{
    protected $signature = 'search:fix-primary-keys {tenant?} {--all} {--force}';
    protected $description = 'Fix primary keys for all search indexes';

    public function handle(TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $all = $this->option('all');
        $force = $this->option('force');

        try {
            $client = app(Client::class);
            
            if ($all) {
                $this->info('Fixing primary keys for all tenants...');
                $tenants = $tenantResolver->getAllTenants();
                
                if (empty($tenants)) {
                    $this->fixIndexesForTenant($client, null);
                } else {
                    foreach ($tenants as $tenantId) {
                        $this->info("Fixing primary keys for tenant: {$tenantId}");
                        $this->fixIndexesForTenant($client, $tenantId);
                    }
                }
            } else {
                if (!$tenant) {
                    $tenant = $tenantResolver->getCurrentTenant();
                }
                $this->fixIndexesForTenant($client, $tenant);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to fix primary keys: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function fixIndexesForTenant(Client $client, ?string $tenant): void
    {
        $config = app('config')->get('global-search');
        $mappings = $config['mappings'] ?? [];
        
        foreach ($mappings as $mapping) {
            $modelClass = $mapping['model'];
            $indexName = $mapping['index'];
            $primaryKey = $mapping['primary_key'] ?? $mapping['primaryKey'] ?? 'id';
            
            // Add tenant suffix if multi-tenant
            if ($tenant) {
                $indexName = "{$indexName}_{$tenant}";
            }
            
            $this->info("Checking index: {$indexName}");
            
            try {
                // Check if index exists
                $index = $client->index($indexName);
                $settings = $index->getSettings();
                $currentPrimaryKey = $settings['primaryKey'] ?? null;
                
                if ($currentPrimaryKey !== $primaryKey) {
                    $this->warn("Index {$indexName} has wrong primary key '{$currentPrimaryKey}', fixing to '{$primaryKey}'");
                    
                    // Delete the old index
                    $task = $client->deleteIndex($indexName);
                    $client->waitForTask($task['taskUid']); // Wait for deletion to complete
                    
                    // Create index with correct primary key
                    $task = $client->createIndex($indexName, ['primaryKey' => $primaryKey]);
                    $client->waitForTask($task['taskUid']); // Wait for creation to complete
                    
                    $this->info("✅ Fixed index {$indexName} with primary key '{$primaryKey}'");
                } else {
                    $this->info("✅ Index {$indexName} already has correct primary key '{$primaryKey}'");
                }
                
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'not found')) {
                    // Index doesn't exist, create it with primary key
                    $this->info("Creating index {$indexName} with primary key '{$primaryKey}'");
                    $task = $client->createIndex($indexName, ['primaryKey' => $primaryKey]);
                    $client->waitForTask($task['taskUid']); // Wait for creation to complete
                    
                    $this->info("✅ Created index {$indexName} with primary key '{$primaryKey}'");
                } else {
                    $this->error("❌ Failed to fix index {$indexName}: {$e->getMessage()}");
                }
            }
        }
    }
}
