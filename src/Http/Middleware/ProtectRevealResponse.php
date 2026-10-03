<?php

namespace Arzcode\SharedSecrets\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectRevealResponse
{
    /**
     * Mail scanners and link previewers issue HEAD requests; answering them
     * without running the page saves the work. A GET never spends a view
     * either: the page only reveals from a later Livewire request. Every
     * other response is marked as uncacheable, unframeable and unindexable, and
     * never leaks the signed URL through the Referer header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $request->isMethod('HEAD') ? response()->noContent() : $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
