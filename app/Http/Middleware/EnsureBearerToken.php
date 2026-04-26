<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBearerToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.face_gateway.api_token');
        $providedToken = $request->bearerToken();

        if ($expectedToken === '') {
            return response()->json([
                'message' => 'Server bearer token is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($providedToken === null || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
