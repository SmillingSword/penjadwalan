<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class TenantIsolation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        
        // Get organization_id from request (header, route parameter, or query)
        $organizationId = $request->header('X-Organization-ID') 
            ?? $request->route('organization_id') 
            ?? $request->get('organization_id');

        if (!$organizationId) {
            return response()->json(['error' => 'Organization ID is required'], 400);
        }

        // Check if user belongs to the organization
        if (!$user->organizations()->where('organization_id', $organizationId)->exists()) {
            return response()->json(['error' => 'Access denied to this organization'], 403);
        }

        // Store organization_id in request for use in controllers
        $request->merge(['current_organization_id' => $organizationId]);

        return $next($request);
    }
}
