<?php

namespace LaravelGlobalSearch\GlobalSearch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;

/**
 * Controller for handling global search API requests.
 */
class GlobalSearchController extends Controller
{
    /**
     * Handle the global search request.
     */
    public function __invoke(Request $request, GlobalSearchService $searchService, TenantResolver $tenantResolver): JsonResponse
    {
        try {
            $validatedData = $this->validateRequest($request);
            
            // Get tenant context
            $tenantId = $validatedData['tenant'] ?? $tenantResolver->getCurrentTenant();
            
            $results = $searchService->search(
                $validatedData['query'],
                $validatedData['filters'],
                $validatedData['limit'],
                $tenantId
            );

            return Response::json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'query' => $validatedData['query'],
                    'limit' => $validatedData['limit'],
                    'filters' => $validatedData['filters'],
                    'tenant' => $tenantId,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Global search request failed', [
                'query' => $request->get('q'),
                'tenant' => $request->get('tenant'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Response::json([
                'success' => false,
                'message' => 'Search request failed',
                'error' => Config::get('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate the search request.
     */
    private function validateRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|max:255',
            'filters' => 'sometimes|array',
            'filters.*' => 'string',
            'limit' => 'sometimes|integer|min:1|max:100',
            'tenant' => 'sometimes|string|max:255',
        ], [
            'q.required' => 'Search query is required',
            'q.string' => 'Search query must be a string',
            'q.max' => 'Search query cannot exceed 255 characters',
            'filters.array' => 'Filters must be an array',
            'filters.*.string' => 'Each filter must be a string',
            'limit.integer' => 'Limit must be an integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 100',
            'tenant.string' => 'Tenant must be a string',
            'tenant.max' => 'Tenant cannot exceed 255 characters',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $query = trim($request->get('q', ''));
        $filters = $request->get('filters', []);
        $tenant = $request->get('tenant');
        
        $defaultLimit = Config::get('global-search.federation.default_limit', 10);
        $maxLimit = Config::get('global-search.federation.max_limit', 50);
        $requestedLimit = (int) $request->get('limit', $defaultLimit);
        
        $limit = min($requestedLimit, $maxLimit);

        return [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
            'tenant' => $tenant,
        ];
    }
}
