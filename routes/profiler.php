<?php

use Phaseolies\Http\Request;
use Doppar\Insight\Profiler;
use Doppar\Insight\Controllers\ProfilerController;

$router = app('route');

// API endpoint for JSON data (used by toolbar panel)
$router->get('/_insight/api/{id}', function (Request $request) {
    /** @var Profiler $profiler */
    $profiler = app(Profiler::class);

    // Extra guard: only serve when globally enabled and IP allowed
    if (! $profiler->isEnabledFor($request)) {
        return ['error' => 'Forbidden']; // Will become JSON 200; keep it simple for now
    }

    $params = $request->getRouteParams();
    $id = $params['id'] ?? null;
    $data = $id ? $profiler->getData($id) : null;

    if (!$data) {
        // Router will turn array into JSON and set 200; to enforce 404 we can use response()->json
        return ['error' => 'Not found'];
    }

    return $data;
});

// Full details page with tabs
$router->get('/_insight/{id}', [ProfilerController::class, 'show']);
