<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSearchAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.auth.login');
        }

        $adminRoles = ['system_admin', 'search_admin', 'analyst', 'read_only'];

        if (! $user->hasAnyRole($adminRoles)) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
