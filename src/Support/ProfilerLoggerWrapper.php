<?php

namespace Doppar\Insight\Support;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Support\LoggerService;

/**
 * Wrapper for LoggerService that persists the Insight profiler handler
 * across reset() calls without modifying the framework
 */
class ProfilerLoggerWrapper extends LoggerService
{
    public function __construct(
        private LoggerService $wrappedLogger,
        private LogHandlerInterface $profilerHandler
    ) {
    }

    /**
     * Override all log methods to re-add handler before each call
     *
     * @param array<string, mixed> $context
     */
    public function info(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->notice($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->warning($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->error($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->debug($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->critical($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->alert($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function emergency(mixed $message, array $context = []): void
    {
        $this->wrappedLogger->addHandler($this->profilerHandler);
        $this->wrappedLogger->emergency($message, $context);
    }

    /**
     * Delegate channel method
     */
    public function channel(string $channel): self
    {
        $this->wrappedLogger->channel($channel);

        return $this;
    }

    /**
     * Delegate addHandler method
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->wrappedLogger->addHandler($handler);
    }
}
