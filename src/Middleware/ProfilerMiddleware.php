<?php

namespace Doppar\Insight\Middleware;

use Closure;
use Doppar\Insight\Collectors\RequestCollector;
use Doppar\Insight\Profiler;
use Doppar\Insight\Support\ErrorHistoryRecorder;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Throwable;

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

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            app(ErrorHistoryRecorder::class)->record($exception, $request);

            throw $exception;
        }

        if ($this->shouldDiscardBootstrapProbe($response, $profiler)) {
            $profiler->discard();

            return $response;
        }

        $profiler->stop($request, $response);

        $response->headers->set('X-Insight-Request-Id', (string) ($profiler->getCurrentData()['id'] ?? ''));
        if (RequestCollector::isAjax($request)) {
            $response->headers->set('X-Insight-Ajax', 'true');
        }

        // If this is a redirect, store the profiler data in session for the next request
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $redirectData = $profiler->getCurrentData();
            // Only the request id goes into the session. The full profile is
            // already persisted by the profiler and is looked up by id on the
            // next request, so the session stays small enough for drivers that
            // keep it in a cookie.
            $id = (string) ($redirectData['id'] ?? '');

            if ($id !== '') {
                $session = $request->session();
                $chain = array_values(array_filter(
                    (array) $session->get('_insight_redirect_chain', []),
                    'is_string'
                ));
                $chain[] = $id;
                $session->put('_insight_redirect_chain', array_slice($chain, -10));
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
            $request->session()->forget('_insight_redirect_chain');
        }

        return $response;
    }

    protected function shouldDiscardBootstrapProbe(Response $response, object $profiler): bool
    {
        if (! method_exists($profiler, 'isRunning') || ! $profiler->isRunning()) {
            return false;
        }

        return $response->statusCode === 200
            && $response->body === null
            && $response->original === null
            && $response->exception === null;
    }
}
