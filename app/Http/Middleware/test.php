<?php

namespace App\Http\Middleware;

use Closure;
use App\Repository\DbCon;
use Illuminate\Support\Facades\Request;

class test{
    
    public function handle($request, Closure $next)
    {
        DbCon::getDb('asdf');
        return $next($request);
    }
    
    
}
