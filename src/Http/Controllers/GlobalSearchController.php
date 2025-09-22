<?php

namespace Selimppc\GlobalSearch\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Selimppc\GlobalSearch\Services\FederatedSearch;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, FederatedSearch $svc)
    {
        $q = (string) $request->get('q', '');
        $filters = (array) $request->get('filters', []);
        $limit = min((int)$request->get('limit', config('global-search.federation.default_limit', 10)), (int)config('global-search.federation.max_limit', 50));
        return response()->json($svc->search($q, $filters, $limit));
    }
}
