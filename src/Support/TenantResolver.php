<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
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
        // If multi-tenancy is disabled, return null
        if (!$this->isMultiTenant()) {
            Log::debug("Multi-tenancy is disabled, returning null");
            return null;
        }

        // First try to get tenant from Stancl/Tenancy if available
        if (class_exists('Stancl\Tenancy\Tenancy')) {
            try {
                $tenancy = app('Stancl\Tenancy\Tenancy');
                if ($tenancy->tenant) {
                    // Get tenant ID from Stancl/Tenancy tenant
                    $tenant = $tenancy->tenant;
                    // Use tenant ID for proper multi-tenant isolation
                    $tenantId = $tenant->id ?? null;
                    Log::debug("Tenant resolved from Stancl/Tenancy: {$tenantId}");
                    return $tenantId;
                }
            } catch (\Exception $e) {
                Log::debug("Stancl/Tenancy not available: {$e->getMessage()}");
                // Fall back to our own resolution
            }
        }

        // Try multiple strategies automatically
        $subdomain = $this->trySubdomain();
        $header = $this->tryHeader();
        $route = $this->tryRoute();
        $query = $this->tryQuery();
        $auth = $this->tryAuth();
        $default = $this->tryDefault();
        
        $tenantId = $subdomain ?? $header ?? $route ?? $query ?? $auth ?? $default;
        
        Log::debug("Tenant resolution attempt", [
            'subdomain' => $subdomain,
            'header' => $header,
            'route' => $route,
            'query' => $query,
            'auth' => $auth,
            'default' => $default,
            'final' => $tenantId
        ]);
        
        return $tenantId;
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
            Log::debug("Multi-tenancy disabled, returning empty tenants array");
            return [];
        }

        // Auto-detect tenants from common sources
        $database = $this->getTenantsFromDatabase();
        $config = $this->getTenantsFromConfig();
        $tenants = $database ?? $config ?? [];
        
        Log::debug("Getting all tenants", [
            'database' => $database,
            'config' => $config,
            'final' => $tenants
        ]);
        
        return $tenants;
    }

    private function trySubdomain(): ?string
    {
        $host = request()->getHost();
        $parts = explode('.', $host);
        
        // If it's an IP address (like 127.0.0.1), return null
        if (count($parts) < 2 || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        
        $subdomain = $parts[0];
        $exclude = ['www', 'api', 'admin', 'app', 'localhost', '127.0.0.1'];
        
        if (in_array($subdomain, $exclude)) return null;
        
        // Try to resolve tenant ID from subdomain
        return $this->resolveTenantIdFromName($subdomain) ?? $subdomain;
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

    private function tryQuery(): ?string
    {
        return request()->get('tenant');
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
        Log::debug("Default tenant resolution", [
            'tenants' => $tenants,
            'first_tenant' => $tenants[0] ?? null
        ]);
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

    private function resolveTenantIdFromName(string $tenantName): ?string
    {
        // Cache tenant resolution for performance
        $cacheKey = "global_search.tenant_resolution.{$tenantName}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenantName) { // 5 minutes cache
            try {
                // Try to find tenant by name - check multiple possible tenant model classes
                $tenantModelClasses = [
                    'Stancl\Tenancy\Models\Tenant',
                    'App\Models\Tenant',
                    config('tenancy.tenant_model'),
                ];
                
                foreach ($tenantModelClasses as $tenantModelClass) {
                    if ($tenantModelClass && class_exists($tenantModelClass)) {
                        $tenant = $tenantModelClass::where('name', 'like', "%{$tenantName}%")
                            ->orWhere('id', $tenantName)
                            ->first();
                        
                        if ($tenant) {
                            return $tenant->id;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Fall back to tenant name if resolution fails
            }
            
            return null;
        });
    }
}
