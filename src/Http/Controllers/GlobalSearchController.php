<?php

namespace LaravelGlobalSearch\GlobalSearch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;

/**
 * Controller for handling global search API requests.
 */
class GlobalSearchController extends Controller
{
    /**
     * Handle the global search request.
     */
    public function __invoke(Request $request, GlobalSearchService $searchService): JsonResponse
    {
        try {
            $validatedData = $this->validateRequest($request);
            
            $results = $searchService->search(
                $validatedData['query'],
                $validatedData['filters'],
                $validatedData['limit']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'query' => $validatedData['query'],
                    'limit' => $validatedData['limit'],
                    'filters' => $validatedData['filters'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Global search request failed', [
                'query' => $request->get('q'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search request failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
        ], [
            'q.required' => 'Search query is required',
            'q.string' => 'Search query must be a string',
            'q.max' => 'Search query cannot exceed 255 characters',
            'filters.array' => 'Filters must be an array',
            'filters.*.string' => 'Each filter must be a string',
            'limit.integer' => 'Limit must be an integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 100',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $query = trim($request->get('q', ''));
        $filters = $request->get('filters', []);
        
        $defaultLimit = config('global-search.federation.default_limit', 10);
        $maxLimit = config('global-search.federation.max_limit', 50);
        $requestedLimit = (int) $request->get('limit', $defaultLimit);
        
        $limit = min($requestedLimit, $maxLimit);

        return [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
        ];
    }
}
