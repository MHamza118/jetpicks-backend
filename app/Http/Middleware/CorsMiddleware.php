<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Allow specific origins
        $allowedOrigins = ['https://app.jetpicks.com', 'http://localhost:5173', 'http://localhost:3000'];

        if (in_array($origin, $allowedOrigins)) {
            $allowOrigin = $origin;
        } else {
            $allowOrigin = 'https://app.jetpicks.com'; // Default to production
        }

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '3600');
        }

        $response = $next($request);

        $response->header('Access-Control-Allow-Origin', $allowOrigin);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '3600');

        return $response;
    }
}
