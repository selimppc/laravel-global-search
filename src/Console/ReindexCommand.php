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
            // Initialize global tenant context - this should be automatic
            $this->initializeGlobalTenantContext($tenantResolver, $tenant);
            
            if ($all) {
                $this->reindexAllTenants($searchService, $tenantResolver, $force);
            } else {
                $this->reindexCurrentContext($searchService, $force);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to reindex: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function initializeGlobalTenantContext(TenantResolver $tenantResolver, ?string $tenant): void
    {
        // If Stancl/Tenancy is available, initialize tenant context globally
        if (class_exists(\Stancl\Tenancy\Tenancy::class)) {
            try {
                $actualTenant = $this->getActualTenantId($tenant);
                if ($actualTenant) {
                    $this->info("🌐 Initializing global tenant context: {$actualTenant}");
                    
                    // Initialize tenant context globally
                    if (function_exists('tenancy')) {
                        tenancy()->initialize($actualTenant);
                    } elseif (class_exists(\Stancl\Tenancy\Tenancy::class)) {
                        app(\Stancl\Tenancy\Tenancy::class)->initialize($actualTenant);
                    }
                    
                    $this->info("✅ Global tenant context initialized successfully");
                } else {
                    $this->warn("⚠️  Could not determine tenant ID. Using landlord database.");
                }
            } catch (\Exception $e) {
                $this->error("❌ Failed to initialize global tenant context: {$e->getMessage()}");
                $this->warn("Continuing with landlord database...");
            }
        } else {
            $this->info("ℹ️  Stancl/Tenancy not detected. Using single-tenant mode.");
        }
    }
    
    private function reindexAllTenants(GlobalSearchService $searchService, TenantResolver $tenantResolver, bool $force): void
    {
        $tenants = $tenantResolver->getAllTenants();
        if (empty($tenants)) {
            $this->info('No tenants found. Reindexing current context...');
            $this->reindexCurrentContext($searchService, $force);
        } else {
            $this->info('Reindexing all tenants...');
            
            foreach ($tenants as $tenantId) {
                $this->info("Reindexing tenant: {$tenantId}");
                $this->reindexTenant($searchService, $tenantId, true);
            }
        }
    }
    
    private function reindexCurrentContext(GlobalSearchService $searchService, bool $force): void
    {
        $this->info("Starting reindex for current context...");
        
        // Get configuration to show what will be indexed
        $config = app('config')->get('global-search');
        $mappings = $config['mappings'] ?? [];
        
        if (empty($mappings)) {
            $this->warn('No model mappings found in configuration. Nothing to reindex.');
            return;
        }
        
        $this->info('Models to be indexed:');
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
        
        $searchService->reindexAll(null);
        
        $this->info('✅ Reindex jobs dispatched successfully!');
        $this->newLine();
        $this->info('💡 To monitor progress:');
        $this->line('  - Check queue: php artisan queue:work');
        $this->line('  - Check logs: tail -f storage/logs/laravel.log');
        $this->line('  - Check health: php artisan search:health');
    }
    
    private function reindexTenant(GlobalSearchService $searchService, ?string $tenant, bool $force): void
    {
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
        
        if ($needsTenantInit) {
            // For Stancl/Tenancy, we need to get the actual tenant ID, not "default"
            $actualTenant = $this->getActualTenantId($tenant);
            if ($actualTenant) {
                $this->info("Initializing tenant context for: {$actualTenant}");
                $this->initializeTenantContext($actualTenant);
            } else {
                $this->warn("Could not determine tenant ID. Using landlord database.");
            }
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
        
        // Always try to initialize tenant context if Stancl/Tenancy is available
        return true;
    }
    
    private function getActualTenantId(?string $tenant): ?string
    {
        // If tenant is "default" or null, try to get the first available tenant
        if ($tenant === 'default' || $tenant === null) {
            try {
                // Method 1: Try to get from database directly
                if (class_exists(\Stancl\Tenancy\Models\Tenant::class)) {
                    $firstTenant = \Stancl\Tenancy\Models\Tenant::first();
                    if ($firstTenant) {
                        return $firstTenant->id ?? $firstTenant->domain ?? $firstTenant->name;
                    }
                }
                
                // Method 2: Try alternative Tenant model locations
                $tenantModels = [
                    \App\Models\Tenant::class,
                    \App\Tenant::class,
                    \Tenant::class,
                ];
                
                foreach ($tenantModels as $modelClass) {
                    if (class_exists($modelClass)) {
                        $firstTenant = $modelClass::first();
                        if ($firstTenant) {
                            return $firstTenant->id ?? $firstTenant->domain ?? $firstTenant->name;
                        }
                    }
                }
                
                // Method 3: Try to get from config
                $tenantConfig = config('tenancy.tenant_model');
                if ($tenantConfig && class_exists($tenantConfig)) {
                    $firstTenant = $tenantConfig::first();
                    if ($firstTenant) {
                        return $firstTenant->id ?? $firstTenant->domain ?? $firstTenant->name;
                    }
                }
                
                // Method 4: Try to get from tenancy() helper (if it has the right methods)
                if (function_exists('tenancy')) {
                    $tenancy = tenancy();
                    if (method_exists($tenancy, 'all')) {
                        $tenants = $tenancy->all();
                        if (!empty($tenants)) {
                            return array_keys($tenants)[0];
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->warn("Failed to get tenant ID: {$e->getMessage()}");
            }
        }
        
        return $tenant;
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
