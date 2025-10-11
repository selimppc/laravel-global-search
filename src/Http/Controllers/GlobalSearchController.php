<?php

namespace LaravelGlobalSearch\GlobalSearch\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;

/**
 * Modern, minimal search controller.
 */
class GlobalSearchController
{
    public function __construct(
        private GlobalSearchService $searchService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $config = config('global-search.federation', []);
        $defaultLimit = $config['default_limit'] ?? 10;
        $maxLimit = $config['max_limit'] ?? 100;
        
        $query = $request->get('q', '');
        $filters = $request->get('filters', []);
        $sort = $request->get('sort', []);
        $limit = min((int) $request->get('limit', $defaultLimit), $maxLimit);
        $offset = max((int) $request->get('offset', 0), 0);
        $tenant = $request->get('tenant');

        $results = $this->searchService->search($query, $filters, $limit, $tenant, $sort, $offset);

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'query' => $query,
                'limit' => $limit,
                'offset' => $offset,
                'tenant' => $results['meta']['tenant'] ?? $tenant,
                'sort' => $sort
            ]
        ]);
    }
}
