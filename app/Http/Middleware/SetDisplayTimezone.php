<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the timezone times are *displayed* in, per request.
 *
 * Timestamps stay stored in the application timezone (UTC) — this only
 * decides how they are read back. The browser reports its own zone into a
 * cookie (see the snippet in the admin layout), so the same data reads
 * correctly from Addis Ababa or anywhere else, with no config to change and
 * no per-user setting to keep in sync.
 *
 * Whatever the browser sends is untrusted, so it is only accepted if it
 * matches a real zone in PHP's database; anything else falls back to the
 * configured default rather than blowing up date handling downstream.
 */
class SetDisplayTimezone
{
    public const COOKIE = 'display_tz';

    public function handle(Request $request, Closure $next)
    {
        $reported = $request->cookie(self::COOKIE);

        if (is_string($reported) && in_array($reported, timezone_identifiers_list(), true)) {
            config(['app.display_timezone' => $reported]);
        }

        return $next($request);
    }
}
