<?php

namespace App\Http\Controllers\InternalApi;

use App\Domain\Search\Services\ProductSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Returns bounded autocomplete suggestions for native JavaScript storefront search. */ public function suggest(Request $request,ProductSearchService $search): JsonResponse { $data=$request->validate(['q'=>['required','string','min:2','max:100']]); return response()->json(['data'=>$search->suggest($data['q'])]); }
}
