<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoMode
{
    /**
     * Block all non-GET requests when demo mode is enabled.
     * This prevents creating, editing, deleting, uploading, and any other
     * state-changing operations on a public demo instance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.enabled') && !$request->isMethod('GET')) {
            return redirect()
                ->back()
                ->with('demo_blocked', 'This action is disabled in demo mode. Download EzTaxes to use it with your own data.');
        }

        return $next($request);
    }
}