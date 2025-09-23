<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;

/**
 * Command to list all available tenants.
 */
class SearchListTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'global-search:list-tenants';

    /**
     * The console command description.
     */
    protected $description = 'List all available tenants';

    /**
     * Execute the console command.
     */
    public function handle(TenantResolver $tenantResolver): int
    {
        if (!$tenantResolver->isMultiTenant()) {
            $this->info('Multi-tenancy is not enabled.');
            return 0;
        }

        $tenants = $tenantResolver->getAllTenants();
        
        if (empty($tenants)) {
            $this->warn('No tenants found.');
            return 0;
        }

        $this->info('Available tenants:');
        $this->newLine();
        
        $headers = ['Tenant ID', 'Index Names'];
        $rows = [];
        
        foreach ($tenants as $tenant) {
            $indexNames = $this->getTenantIndexNames($tenantResolver, $tenant);
            $rows[] = [$tenant, implode(', ', $indexNames)];
        }
        
        $this->table($headers, $rows);
        
        return 0;
    }

    /**
     * Get index names for a tenant.
     */
    private function getTenantIndexNames(TenantResolver $tenantResolver, string $tenant): array
    {
        $config = Config::get('global-search');
        $mappings = $config['mappings'] ?? [];
        $indexNames = [];
        
        foreach ($mappings as $mapping) {
            $indexName = $mapping['index'] ?? null;
            if ($indexName) {
                $indexNames[] = $tenantResolver->getTenantIndexName($indexName);
            }
        }
        
        return $indexNames;
    }
}
