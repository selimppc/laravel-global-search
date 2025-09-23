<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class ReindexCommand extends Command
{
    protected $signature = 'search:reindex {tenant?} {--force} {--all}';
    protected $description = 'Reindex all models for search';

    public function handle(GlobalSearchService $searchService, TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $force = $this->option('force');
        $all = $this->option('all');

        try {
            if ($all) {
                // Reindex all tenants
                $tenants = $tenantResolver->getAllTenants();
                if (empty($tenants)) {
                    $this->info('No tenants found. Reindexing default index...');
                    $this->reindexTenant($searchService, null, $force);
                } else {
                    if (!$force && !$this->confirm('This will reindex all models for all tenants. Continue?')) {
                        $this->info('Reindex cancelled.');
                        return Command::SUCCESS;
                    }
                    
                    foreach ($tenants as $tenantId) {
                        $this->info("Reindexing tenant: {$tenantId}");
                        $this->reindexTenant($searchService, $tenantId, true);
                    }
                }
            } else {
                $this->reindexTenant($searchService, $tenant, $force);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to reindex: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function reindexTenant(GlobalSearchService $searchService, ?string $tenant, bool $force): void
    {
        if (!$force && !$this->confirm("This will reindex all models for tenant: " . ($tenant ?? 'default') . ". Continue?")) {
            $this->info('Reindex cancelled.');
            return;
        }

        $this->info("Starting reindex for tenant: " . ($tenant ?? 'default'));
        $searchService->reindexAll($tenant);
        $this->info('Reindex jobs dispatched successfully!');
    }
}
