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
        $query = $request->get('q', '');
        $filters = $request->get('filters', []);
        $limit = min((int) $request->get('limit', 10), 50);
        $tenant = $request->get('tenant');

        $results = $this->searchService->search($query, $filters, $limit, $tenant);

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'query' => $query,
                'limit' => $limit,
                'tenant' => $tenant
            ]
        ]);
    }
}
