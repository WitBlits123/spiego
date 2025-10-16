<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip license check for license-related routes
        if ($request->is('license') || $request->is('license/*')) {
            return $next($request);
        }

        // Check if license is valid or trial is active
        if (!LicenseService::isValid()) {
            // Redirect to trial expired page
            return redirect()->route('license.expired');
        }

        return $next($request);
    }
}
