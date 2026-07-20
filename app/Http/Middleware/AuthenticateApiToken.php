<?php

namespace App\Http\Middleware;

use App\Models\StoreApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (!$plain) {
            return response()->json(['error' => 'Missing API token'], 401);
        }

        $hashed = hash('sha256', $plain);
        $token  = StoreApiToken::where('token', $hashed)->first();

        if (!$token) {
            return response()->json(['error' => 'Invalid API token'], 401);
        }

        \DB::table('store_api_tokens')
            ->where('id', $token->id)
            ->update(['last_used_at' => now()]);

        $request->merge(['store_id' => $token->store_id]);

        return $next($request);
    }
}
