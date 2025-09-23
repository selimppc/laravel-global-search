<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Default tenant resolver implementation.
 * 
 * This resolver supports multiple tenant detection strategies:
 * - Subdomain-based (tenant.example.com)
 * - Header-based (X-Tenant-ID header)
 * - Route parameter-based
 * - Custom resolver closure
 */
class DefaultTenantResolver implements TenantResolver
{
    /**
     * Create a new tenant resolver instance.
     */
    public function __construct(
        private array $config
    ) {}

    /**
     * Get the current tenant identifier.
     */
    public function getCurrentTenant(): ?string
    {
        if (!$this->isMultiTenant()) {
            return null;
        }

        $strategies = $this->config['tenant']['strategies'] ?? [];
        
        foreach ($strategies as $strategy) {
            $tenant = $this->resolveTenantByStrategy($strategy);
            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Check if multi-tenancy is enabled.
     */
    public function isMultiTenant(): bool
    {
        return (bool) ($this->config['tenant']['enabled'] ?? false);
    }

    /**
     * Get the tenant-specific index name.
     */
    public function getTenantIndexName(string $baseIndexName): string
    {
        if (!$this->isMultiTenant()) {
            return $baseIndexName;
        }

        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            throw new \RuntimeException('Multi-tenancy is enabled but no tenant context found');
        }

        $separator = $this->config['tenant']['index_separator'] ?? '_';
        return $baseIndexName . $separator . 'tenant' . $separator . $tenant;
    }

    /**
     * Get all tenant identifiers.
     */
    public function getAllTenants(): array
    {
        if (!$this->isMultiTenant()) {
            return [];
        }

        $tenantSource = $this->config['tenant']['source'] ?? 'database';
        
        switch ($tenantSource) {
            case 'database':
                return $this->getTenantsFromDatabase();
            case 'config':
                return $this->getTenantsFromConfig();
            case 'custom':
                return $this->getTenantsFromCustom();
            default:
                return [];
        }
    }

    /**
     * Resolve tenant using a specific strategy.
     */
    private function resolveTenantByStrategy(array $strategy): ?string
    {
        $type = $strategy['type'] ?? null;
        
        switch ($type) {
            case 'subdomain':
                return $this->resolveFromSubdomain($strategy);
            case 'header':
                return $this->resolveFromHeader($strategy);
            case 'route':
                return $this->resolveFromRoute($strategy);
            case 'custom':
                return $this->resolveFromCustom($strategy);
            default:
                return null;
        }
    }

    /**
     * Resolve tenant from subdomain.
     */
    private function resolveFromSubdomain(array $strategy): ?string
    {
        $request = \Illuminate\Support\Facades\App::make(Request::class);
        $host = $request->getHost();
        
        $pattern = $strategy['pattern'] ?? '^([^.]+)\.';
        if (!preg_match("/{$pattern}/", $host, $matches)) {
            return null;
        }
        
        $tenant = $matches[1] ?? null;
        $exclude = $strategy['exclude'] ?? ['www', 'api', 'admin'];
        
        return in_array($tenant, $exclude) ? null : $tenant;
    }

    /**
     * Resolve tenant from HTTP header.
     */
    private function resolveFromHeader(array $strategy): ?string
    {
        $request = \Illuminate\Support\Facades\App::make(Request::class);
        $headerName = $strategy['header'] ?? 'X-Tenant-ID';
        
        return $request->header($headerName);
    }

    /**
     * Resolve tenant from route parameter.
     */
    private function resolveFromRoute(array $strategy): ?string
    {
        $request = \Illuminate\Support\Facades\App::make(Request::class);
        $parameter = $strategy['parameter'] ?? 'tenant';
        
        return $request->route($parameter);
    }

    /**
     * Resolve tenant using custom closure.
     */
    private function resolveFromCustom(array $strategy): ?string
    {
        $resolver = $strategy['resolver'] ?? null;
        
        if (is_callable($resolver)) {
            return $resolver();
        }
        
        return null;
    }

    /**
     * Get tenants from database.
     */
    private function getTenantsFromDatabase(): array
    {
        $model = $this->config['tenant']['model'] ?? null;
        $column = $this->config['tenant']['identifier_column'] ?? 'id';
        
        if (!$model || !class_exists($model)) {
            return [];
        }
        
        try {
            return $model::query()->pluck($column)->toArray();
        } catch (\Exception $e) {
            \Log::warning('Failed to get tenants from database', [
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get tenants from configuration.
     */
    private function getTenantsFromConfig(): array
    {
        return $this->config['tenant']['list'] ?? [];
    }

    /**
     * Get tenants using custom resolver.
     */
    private function getTenantsFromCustom(): array
    {
        $resolver = $this->config['tenant']['list_resolver'] ?? null;
        
        if (is_callable($resolver)) {
            return $resolver();
        }
        
        return [];
    }
}
