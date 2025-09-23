<?php

namespace LaravelGlobalSearch\GlobalSearch\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

/**
 * Middleware to automatically initialize tenant context for search routes.
 * This ensures the search API works seamlessly without requiring explicit tenant parameters.
 */
class InitializeTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        // Only initialize tenant context for search routes
        if ($this->isSearchRoute($request)) {
            $tenantId = $this->resolveTenantId($request);
            
            if ($tenantId) {
                $this->initializeTenantContext($tenantId);
            }
        }

        return $next($request);
    }

    private function isSearchRoute(Request $request): bool
    {
        // Check if this is a search API route
        return $request->is('global-search*') || 
               $request->is('api/global-search*') ||
               $request->is('search*');
    }

    private function resolveTenantId(Request $request): ?string
    {
        // Method 1: From subdomain (most common)
        $tenantId = $this->resolveFromSubdomain($request);
        if ($tenantId) return $tenantId;

        // Method 2: From header
        $tenantId = $this->resolveFromHeader($request);
        if ($tenantId) return $tenantId;

        // Method 3: From route parameter
        $tenantId = $this->resolveFromRoute($request);
        if ($tenantId) return $tenantId;

        // Method 4: From query parameter (fallback)
        $tenantId = $this->resolveFromQuery($request);
        if ($tenantId) return $tenantId;

        return null;
    }

    private function resolveFromSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) < 2) return null;
        
        $subdomain = $parts[0];
        $exclude = ['www', 'api', 'admin', 'app', 'localhost', '127.0.0.1'];
        
        if (in_array($subdomain, $exclude)) return null;
        
        return $this->findTenantIdByName($subdomain);
    }

    private function resolveFromHeader(Request $request): ?string
    {
        $tenantName = $request->header('X-Tenant') ?? $request->header('Tenant');
        return $tenantName ? $this->findTenantIdByName($tenantName) : null;
    }

    private function resolveFromRoute(Request $request): ?string
    {
        $tenantName = $request->route('tenant');
        return $tenantName ? $this->findTenantIdByName($tenantName) : null;
    }

    private function resolveFromQuery(Request $request): ?string
    {
        $tenantName = $request->get('tenant');
        return $tenantName ? $this->findTenantIdByName($tenantName) : null;
    }

    private function findTenantIdByName(string $tenantName): ?string
    {
        try {
            // Try multiple possible tenant model classes
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
            // Silently fail - tenant resolution is optional
        }
        
        return null;
    }

    private function initializeTenantContext(string $tenantId): void
    {
        try {
            // Try to initialize tenant context using Stancl/Tenancy
            if (class_exists(Tenancy::class)) {
                app(Tenancy::class)->initialize($tenantId);
            } elseif (function_exists('tenancy')) {
                tenancy()->initialize($tenantId);
            }
        } catch (\Exception $e) {
            // Silently fail - tenant initialization is optional
        }
    }
}
