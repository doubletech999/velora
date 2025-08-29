<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is logged in and is admin
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            // If accessing admin panel via web, redirect to login
            if (!$request->expectsJson()) {
                return redirect()->route('admin.login');
            }
            
            // If API request, return unauthorized
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}