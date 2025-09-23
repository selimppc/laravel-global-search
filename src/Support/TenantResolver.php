<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Modern tenant resolver that auto-detects tenant context without requiring model changes.
 */
class TenantResolver
{
    public function __construct(
        private array $config
    ) {}

    public function getCurrentTenant(): ?string
    {
        // First try to get tenant from Stancl/Tenancy if available
        if (class_exists('Stancl\Tenancy\Tenancy')) {
            try {
                $tenancy = app('Stancl\Tenancy\Tenancy');
                if ($tenancy->tenant) {
                    // Get tenant ID from Stancl/Tenancy tenant
                    $tenant = $tenancy->tenant;
                    // Use tenant ID for proper multi-tenant isolation
                    return $tenant->id ?? null;
                }
            } catch (\Exception $e) {
                // Fall back to our own resolution
            }
        }

        // Try multiple strategies automatically
        return $this->trySubdomain() 
            ?? $this->tryHeader() 
            ?? $this->tryRoute() 
            ?? $this->tryAuth() 
            ?? $this->tryDefault();
    }

    public function isMultiTenant(): bool
    {
        return $this->config['tenant']['enabled'] ?? false;
    }

    public function getTenantIndexName(string $baseIndexName, ?string $tenant = null): string
    {
        if (!$this->isMultiTenant()) {
            return $baseIndexName;
        }

        $tenant = $tenant ?? $this->getCurrentTenant();
        return $tenant ? "{$baseIndexName}_{$tenant}" : $baseIndexName;
    }

    public function getAllTenants(): array
    {
        if (!$this->isMultiTenant()) {
            return [];
        }

        // Auto-detect tenants from common sources
        return $this->getTenantsFromDatabase() 
            ?? $this->getTenantsFromConfig() 
            ?? [];
    }

    private function trySubdomain(): ?string
    {
        $host = request()->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) < 2) return null;
        
        $subdomain = $parts[0];
        $exclude = ['www', 'api', 'admin', 'app', 'localhost'];
        
        return in_array($subdomain, $exclude) ? null : $subdomain;
    }

    private function tryHeader(): ?string
    {
        return request()->header('X-Tenant-ID') 
            ?? request()->header('Tenant-ID');
    }

    private function tryRoute(): ?string
    {
        return request()->route('tenant') 
            ?? request()->route('domain');
    }

    private function tryAuth(): ?string
    {
        $user = auth()->user();
        return $user?->tenant_id 
            ?? $user?->domain 
            ?? $user?->subdomain;
    }

    private function tryDefault(): ?string
    {
        // Get first available tenant as default
        $tenants = $this->getAllTenants();
        return $tenants[0] ?? null;
    }

    private function getTenantsFromDatabase(): ?array
    {
        try {
            $model = $this->config['tenant']['model'] ?? null;
            if (!$model || !class_exists($model)) return null;

            $identifier = $this->config['tenant']['identifier_column'] ?? 'name';
            
            // Try to get a meaningful identifier, fallback to id if needed
            $tenants = $model::select($identifier)->get();
            return $tenants->pluck($identifier)->filter()->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get tenants from database', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function getTenantsFromConfig(): ?array
    {
        return $this->config['tenant']['list'] ?? null;
    }
}
