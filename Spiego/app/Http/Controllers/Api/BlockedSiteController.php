<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlockedSite;

class BlockedSiteController extends Controller
{
    /**
     * Return blocked domains for a given hostname
     * GET /api/blocked_sites?hostname=...
     */
    public function index(Request $request)
    {
        // Bearer token auth (same method as EventController)
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($authHeader, 7);
        $expectedKey = env('AUTH_KEY', 'your-secret-auth-key-change-me');

        if ($token !== $expectedKey) {
            return response()->json(['error' => 'Invalid authentication key'], 401);
        }

        $hostname = $request->query('hostname');
        if (!$hostname) {
            return response()->json(['error' => 'hostname parameter required'], 400);
        }

        $domains = BlockedSite::where('hostname', $hostname)->orderBy('domain')->pluck('domain')->toArray();

        return response()->json(['blocked' => $domains], 200);
    }
}
