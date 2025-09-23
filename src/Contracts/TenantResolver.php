<?php

namespace LaravelGlobalSearch\GlobalSearch\Contracts;

/**
 * Contract for resolving the current tenant context.
 */
interface TenantResolver
{
    /**
     * Get the current tenant identifier.
     * 
     * @return string|null The tenant identifier or null if no tenant context
     */
    public function getCurrentTenant(): ?string;

    /**
     * Check if multi-tenancy is enabled.
     * 
     * @return bool True if multi-tenancy is enabled
     */
    public function isMultiTenant(): bool;

    /**
     * Get the tenant-specific index name.
     * 
     * @param string $baseIndexName The base index name
     * @return string The tenant-specific index name
     */
    public function getTenantIndexName(string $baseIndexName): string;

    /**
     * Get all tenant identifiers (for operations across all tenants).
     * 
     * @return array Array of tenant identifiers
     */
    public function getAllTenants(): array;
}
