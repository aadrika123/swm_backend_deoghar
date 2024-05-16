<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
class ApiAuth extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    // protected function redirectTo($request)
    // {
    //     if (!$request->expectsJson()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'No Bearer Token',
    //         ], 400);
    //     }
    // }

    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json(['error' => 'Unauthenticated.'], 401));
    }

    
}
