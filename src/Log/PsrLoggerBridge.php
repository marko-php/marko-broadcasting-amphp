<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Log;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Stringable;

/**
 * Forwards PSR-3 records from amphp/http-server to the Marko logger, so server internals land
 * in the same log as the rest of the application.
 */
class PsrLoggerBridge extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<array-key, mixed> $context
     * @throws InvalidArgumentException
     */
    public function log(
        mixed $level,
        string|Stringable $message,
        array $context = [],
    ): void {
        $logLevel = is_string($level) ? LogLevel::tryFrom($level) : null;

        if ($logLevel === null) {
            throw new InvalidArgumentException(
                'Unknown PSR-3 log level: ' . (is_string($level) ? $level : get_debug_type($level)),
            );
        }

        $this->logger->log($logLevel, (string) $message, $context);
    }
}
