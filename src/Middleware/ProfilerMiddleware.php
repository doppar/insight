<?php

namespace Doppar\Insight\Middleware;

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Doppar\Insight\Profiler;

class ProfilerMiddleware implements \Phaseolies\Middleware\Contracts\Middleware
{
    public function __invoke(Request $request, Closure $next): Response
    {
        /** @var Profiler $profiler */
        $profiler = app(Profiler::class);

        if (! $profiler->isEnabledFor($request)) {
            return $next($request);
        }

        // Skip profiling for profiler routes themselves
        $path = $request->getPath();
        if (str_starts_with($path, '/_insight')) {
            return $next($request);
        }

        $profiler->start($request);
        $response = $next($request);
        $profiler->stop($request, $response);

        // If this is a redirect, store the profiler data in session for the next request
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $redirectData = $profiler->getCurrentData();
            if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
                $_SESSION['_insight_redirect_chain'] = $_SESSION['_insight_redirect_chain'] ?? [];
                $_SESSION['_insight_redirect_chain'][] = $redirectData;
            }
        }

        if ($profiler->shouldInject($response)) {
            $body = $response->body ?? '';
            $toolbar = $profiler->renderToolbar();
            $injected = preg_replace('/<\/body>/i', $toolbar . '</body>', $body, 1);
            if ($injected === null) {
                $injected = $body . $toolbar;
            }
            $response->setBody($injected);
            
            // Clear redirect chain from session after rendering toolbar
            // (it's already saved in profiler data)
            if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
                unset($_SESSION['_insight_redirect_chain']);
            }
        }

        return $response;
    }
}
