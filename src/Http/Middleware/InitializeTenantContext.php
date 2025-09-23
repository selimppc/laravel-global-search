<?php

namespace LaravelGlobalSearch\GlobalSearch\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Stancl\Tenancy\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to automatically initialize tenant context for search routes.
 * This ensures the search API works seamlessly without requiring explicit tenant parameters.
 */
class InitializeTenantContext
{
    public function __construct(private TenantResolver $tenantResolver) {}

    public function handle(Request $request, Closure $next)
    {
        // Only initialize tenant context for search routes
        if ($this->isSearchRoute($request)) {
            Log::debug("Middleware processing search route", [
                'url' => $request->url(),
                'host' => $request->getHost(),
                'subdomain' => $this->extractSubdomain($request),
                'headers' => $request->headers->all()
            ]);
            
            $tenantId = $this->tenantResolver->getCurrentTenant();
            
            if ($tenantId) {
                $this->initializeTenantContext($tenantId);
                Log::info("Tenant context initialized by middleware: {$tenantId}");
            } else {
                Log::debug("No tenant ID resolved by middleware.");
            }
        }

        return $next($request);
    }
    
    private function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) < 2) return null;
        
        $subdomain = $parts[0];
        $exclude = ['www', 'api', 'admin', 'app', 'localhost', '127.0.0.1'];
        
        return in_array($subdomain, $exclude) ? null : $subdomain;
    }

    private function isSearchRoute(Request $request): bool
    {
        // Check if this is a search API route
        return $request->is('global-search*') || 
               $request->is('api/global-search*') ||
               $request->is('search*');
    }


    private function initializeTenantContext(string $tenantId): void
    {
        try {
            // Check if tenant is already initialized
            if (class_exists(Tenancy::class)) {
                $tenancy = app(Tenancy::class);
                if ($tenancy->tenant && $tenancy->tenant->id === $tenantId) {
                    return; // Already initialized with correct tenant
                }
                
                // Find and initialize the tenant
                $tenantModel = config('tenancy.tenant_model') ?? \Stancl\Tenancy\Models\Tenant::class;
                $tenant = $tenantModel::find($tenantId);
                
                if ($tenant) {
                    $tenancy->initialize($tenant);
                } else {
                    Log::warning("Tenant not found for ID: {$tenantId}");
                }
            } elseif (function_exists('tenancy')) {
                tenancy()->initialize($tenantId);
            }
        } catch (\Exception $e) {
            Log::error("Failed to initialize tenant context in middleware: {$e->getMessage()}");
        }
    }
}
