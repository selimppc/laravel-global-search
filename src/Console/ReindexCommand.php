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
        
        // Get configuration to show what will be indexed
        $config = app('config')->get('global-search');
        $mappings = $config['mappings'] ?? [];
        
        if (empty($mappings)) {
            $this->warn('No model mappings found in configuration. Nothing to reindex.');
            return;
        }
        
        $this->info('Models to be indexed:');
        
        // Check if we need to initialize tenant context (Stancl/Tenancy)
        $needsTenantInit = $this->needsTenantInitialization($tenant);
        
        if ($needsTenantInit && $tenant) {
            $this->info("Initializing tenant context for: {$tenant}");
            $this->initializeTenantContext($tenant);
        }
        
        foreach ($mappings as $mapping) {
            $modelClass = $mapping['model'];
            if (class_exists($modelClass)) {
                $count = $modelClass::count();
                $this->line("  - {$modelClass}: {$count} records");
            } else {
                $this->warn("  - {$modelClass}: Class not found (skipped)");
            }
        }
        
        $this->newLine();
        $this->info('Dispatching reindex jobs...');
        
        $searchService->reindexAll($tenant);
        
        $this->info('✅ Reindex jobs dispatched successfully!');
        $this->newLine();
        $this->info('💡 To monitor progress:');
        $this->line('  - Check queue: php artisan queue:work');
        $this->line('  - Check logs: tail -f storage/logs/laravel.log');
        $this->line('  - Check health: php artisan search:health');
    }
    
    private function needsTenantInitialization(?string $tenant): bool
    {
        // Check if Stancl/Tenancy is available
        if (!class_exists(\Stancl\Tenancy\Tenancy::class)) {
            return false;
        }
        
        // Check if we have a tenant and multi-tenancy is not explicitly disabled
        return $tenant !== null;
    }
    
    private function initializeTenantContext(string $tenant): void
    {
        try {
            // Try to initialize tenant context using Stancl/Tenancy
            if (function_exists('tenancy')) {
                tenancy()->initialize($tenant);
                $this->info("✅ Tenant context initialized: {$tenant}");
            } elseif (class_exists(\Stancl\Tenancy\Tenancy::class)) {
                app(\Stancl\Tenancy\Tenancy::class)->initialize($tenant);
                $this->info("✅ Tenant context initialized: {$tenant}");
            } else {
                $this->warn("⚠️  Could not initialize tenant context. Make sure Stancl/Tenancy is properly installed.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Failed to initialize tenant context: {$e->getMessage()}");
            $this->warn("Continuing with landlord database...");
        }
    }
}
