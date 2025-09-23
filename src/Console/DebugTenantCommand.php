<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;

class DebugTenantCommand extends Command
{
    protected $signature = 'search:debug-tenant';
    protected $description = 'Debug tenant resolution for global search';

    public function handle(TenantResolver $tenantResolver): void
    {
        $this->info('🔍 Debugging Tenant Resolution');
        $this->newLine();

        // Check if multi-tenancy is enabled
        $isMultiTenant = $tenantResolver->isMultiTenant();
        $this->line("Multi-tenancy enabled: " . ($isMultiTenant ? '✅ Yes' : '❌ No'));

        if (!$isMultiTenant) {
            $this->warn('Multi-tenancy is disabled. Set GLOBAL_SEARCH_MULTI_TENANT=true in your .env file.');
            return;
        }

        $this->newLine();

        // Get current tenant
        $currentTenant = $tenantResolver->getCurrentTenant();
        $this->line("Current tenant: " . ($currentTenant ?: 'None'));

        // Get all tenants
        $allTenants = $tenantResolver->getAllTenants();
        $this->line("All tenants: " . (empty($allTenants) ? 'None found' : implode(', ', $allTenants)));

        $this->newLine();

        // Check request details
        $request = request();
        $this->line("Request details:");
        $this->line("- Host: " . $request->getHost());
        $this->line("- URL: " . $request->url());
        $this->line("- Subdomain: " . $this->extractSubdomain($request));
        $this->line("- Headers: " . json_encode($request->headers->all()));

        $this->newLine();

        // Check Stancl/Tenancy
        if (class_exists('Stancl\Tenancy\Tenancy')) {
            try {
                $tenancy = app('Stancl\Tenancy\Tenancy');
                $this->line("Stancl/Tenancy available: ✅ Yes");
                $this->line("Current tenant in Tenancy: " . ($tenancy->tenant ? $tenancy->tenant->id : 'None'));
            } catch (\Exception $e) {
                $this->line("Stancl/Tenancy available: ❌ No - " . $e->getMessage());
            }
        } else {
            $this->line("Stancl/Tenancy available: ❌ No");
        }

        $this->newLine();
        $this->info('Debug complete!');
    }

    private function extractSubdomain($request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) < 2) return null;
        
        $subdomain = $parts[0];
        $exclude = ['www', 'api', 'admin', 'app', 'localhost', '127.0.0.1'];
        
        return in_array($subdomain, $exclude) ? null : $subdomain;
    }
}
