<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class ReindexTenantCommand extends Command
{
    protected $signature = 'search:reindex-tenant {tenant} {--force}';
    protected $description = 'Reindex all models for a specific tenant';

    public function handle(GlobalSearchService $searchService, TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $force = $this->option('force');

        if (!$force && !$this->confirm("This will reindex all models for tenant '{$tenant}'. Continue?")) {
            $this->info('Reindex cancelled.');
            return Command::SUCCESS;
        }

        try {
            $this->info("Starting reindex for tenant: {$tenant}");
            
            // Verify tenant exists
            $allTenants = $tenantResolver->getAllTenants();
            if (!empty($allTenants) && !in_array($tenant, $allTenants)) {
                $this->error("Tenant '{$tenant}' not found. Available tenants: " . implode(', ', $allTenants));
                return Command::FAILURE;
            }
            
            $searchService->reindexAll($tenant);
            $this->info('Reindex jobs dispatched successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to reindex tenant '{$tenant}': {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
