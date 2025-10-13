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

        $profiler->start($request);
        $response = $next($request);
        $profiler->stop($request, $response);

        // If this is a redirect, store the profiler data in session for the next request
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $redirectData = $profiler->getCurrentData();
            if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
                $_SESSION['_profiler_redirect_chain'] = $_SESSION['_profiler_redirect_chain'] ?? [];
                $_SESSION['_profiler_redirect_chain'][] = $redirectData;
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
        }

        return $response;
    }
}
