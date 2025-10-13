<?php

namespace Doppar\Insight\Controllers;

use Phaseolies\Http\Request;
use Doppar\Insight\Profiler;

class ProfilerController
{
    public function show(Request $request, string $id)
    {
        /** @var Profiler $profiler */
        $profiler = app(Profiler::class);

        // Extra guard: only serve when globally enabled and IP allowed
        if (! $profiler->isEnabledFor($request)) {
            return ['error' => 'Forbidden'];
        }

        $data = $profiler->getData($id);

        if (!$data) {
            return ['error' => 'Not found'];
        }

        // Render the details page
        return $this->renderDetailsPage($data);
    }

    protected function renderDetailsPage(array $data): string
    {
        $stubPath = __DIR__ . '/../../resources/stubs/details.html';
        $template = is_file($stubPath) ? file_get_contents($stubPath) : '';

        if ($template === '') {
            return '<html><body>Details page template not found</body></html>';
        }

        // Build CSS and JS using AssetBuilder
        $builder = new \Doppar\Insight\AssetBuilder();
        $css = $builder->buildCss();
        $js = $builder->buildJs();

        // Prepare data for template
        $id = htmlspecialchars($data['id'] ?? '', ENT_QUOTES, 'UTF-8');
        $method = htmlspecialchars($data['method'] ?? '', ENT_QUOTES, 'UTF-8');
        $route = htmlspecialchars($data['route'] ?? '', ENT_QUOTES, 'UTF-8');
        $status = (int)($data['status'] ?? 0);
        $duration = number_format($data['duration_ms'] ?? 0, 2);
        $memoryPeak = number_format(($data['memory_peak'] ?? 0) / (1024*1024), 2);
        $frameworkVersion = htmlspecialchars($data['framework_version'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $phpVersion = htmlspecialchars($data['php_version'] ?? PHP_VERSION, ENT_QUOTES, 'UTF-8');
        
        // SQL data
        $sqlTotalCount = (int)($data['sql_total_count'] ?? 0);
        $sqlTotalTime = number_format($data['sql_total_time_ms'] ?? 0, 2);
        
        // Encode data as JSON for JavaScript
        $dataJson = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $replacements = [
            '{{CSS}}' => $css,
            '{{JS}}' => $js,
            '{{ID}}' => $id,
            '{{METHOD}}' => $method,
            '{{ROUTE}}' => $route,
            '{{STATUS}}' => (string)$status,
            '{{DURATION}}' => $duration,
            '{{MEMORY_PEAK}}' => $memoryPeak,
            '{{FRAMEWORK_VERSION}}' => $frameworkVersion,
            '{{PHP_VERSION}}' => $phpVersion,
            '{{SQL_TOTAL_COUNT}}' => (string)$sqlTotalCount,
            '{{SQL_TOTAL_TIME}}' => $sqlTotalTime,
            '{{DATA_JSON}}' => $dataJson,
        ];

        return strtr($template, $replacements);
    }
}
