<?php

namespace Doppar\Insight\Contracts;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

interface CollectorInterface
{
    public function name(): string;

    public function start(Request $request): void;

    public function stop(Request $request, Response $response): void;

    /**
     * Return scalar/array data for this collector
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
