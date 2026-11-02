<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request and ensure all CORS headers are set.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        $allowedOrigins = [
            'https://salonvellora.com.lk',
            'http://salonvellora.com.lk',
            'https://api.salonvellora.com.lk',
            'http://api.salonvellora.com.lk',
            'http://localhost:3000',
            'http://127.0.0.1:3000'
        ];

        // If origin is in allowed list, reflect it; otherwise use wildcard
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : '*';

        // Preflight OPTIONS requests handled immediately with 200 OK
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
