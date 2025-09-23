<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class FlushCommand extends Command
{
    protected $signature = 'search:flush {tenant?} {--all} {--force}';
    protected $description = 'Flush all documents from search indexes';

    public function handle(GlobalSearchService $searchService, TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $all = $this->option('all');
        $force = $this->option('force');

        try {
            if ($all) {
                // Flush all tenants
                $tenants = $tenantResolver->getAllTenants();
                if (empty($tenants)) {
                    $this->info('No tenants found. Flushing default index...');
                    $this->flushTenant($searchService, null, $force);
                } else {
                    $this->info('Flushing all tenants...');
                    
                    foreach ($tenants as $tenantId) {
                        $this->info("Flushing tenant: {$tenantId}");
                        $this->flushTenant($searchService, $tenantId, true);
                    }
                }
            } else {
                $this->flushTenant($searchService, $tenant, $force);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to flush: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function flushTenant(GlobalSearchService $searchService, ?string $tenant, bool $force): void
    {
        $this->info("Starting flush for tenant: " . ($tenant ?? 'default'));
        $searchService->flushAll($tenant);
        $this->info('Flush completed successfully!');
    }
}
