<?php

namespace Hybridcore\Demo\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DemoController extends Controller
{
    /** Requires an API token with the demo:ping ability. See BUILDING_EXTENSIONS.md's "API Token Abilities" section. */
    public function ping(): JsonResponse
    {
        return response()->json(['message' => 'pong']);
    }
}
