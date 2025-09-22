<?php

namespace LaravelGlobalSearch\GlobalSearch\Contracts;

/**
 * Contract for resolving related links for search results.
 */
interface SearchResultLinkResolver
{
    /**
     * Resolve related links for a search result hit.
     *
     * @param array $hit The search result hit data
     * @return array Array of link objects with 'label' and 'href' keys
     */
    public function resolve(array $hit): array;
}
