<?php

namespace App\Core\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UseArabicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');
        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
