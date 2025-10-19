<?php

namespace Doppar\Insight\Handlers;

use Doppar\Insight\Collectors\LogCollector;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Custom Monolog handler that intercepts logs and sends them to the LogCollector
 */
class ProfilerLogHandler extends AbstractProcessingHandler
{
    /**
     * Write the log record to the LogCollector
     *
     * @param LogRecord $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $collector = LogCollector::active();

        if (! $collector) {
            return;
        }

        $collector->registerLog(
            level: strtolower($record->level->getName()),
            message: $record->message,
            context: $record->context,
            timestamp: $record->datetime->getTimestamp() + ($record->datetime->format('u') / 1000000)
        );
    }
}
