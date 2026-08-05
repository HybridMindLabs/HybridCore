<?php

namespace Hybridcore\Demo\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Extensions\Registries\HookRegistry;
use Illuminate\Http\JsonResponse;

class DemoController extends Controller
{
    /** Requires an API token with the demo:ping ability. See BUILDING_EXTENSIONS.md's "API Token Abilities" section. */
    public function ping(HookRegistry $hooks): JsonResponse
    {
        // Reuses the "user.login" style handler in DemoServiceProvider — but this
        // one is the extension's own event ("demo.pinged"), registered with
        // webhookEvents() there. Firing it is all it takes to also reach any
        // admin-subscribed webhook endpoint.
        $hooks->fire('demo.pinged');

        return response()->json(['message' => 'pong']);
    }
}
