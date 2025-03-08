<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Get locale from session or default to config
        $locale = Session::get('locale', config('app.locale'));

        // Verify the locale is valid
        if (!in_array($locale, config('app.available_locales', ['en']))) {
            $locale = config('app.locale');
        }

        // Set the application locale
        App::setLocale($locale);
        Session::put('locale', $locale);

        // Share the locale with all views
        view()->share('currentLocale', $locale);

        return $next($request);
    }
}
