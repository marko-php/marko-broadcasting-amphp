<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Tests\Support;

use function Amp\delay;

use RuntimeException;

/**
 * Waits for a condition instead of for a fixed duration. Polling with delay() keeps the event
 * loop running, so server fibers make progress between checks. The timeout is only an upper
 * bound: a passing test returns as soon as the condition holds, however loaded the machine is.
 */
class Poll
{
    /**
     * @param callable(): bool $condition
     * @param string $description What is being waited for, e.g. "the subscription to be cancelled"
     * @throws RuntimeException When the condition does not hold within $timeout seconds
     */
    public static function until(
        callable $condition,
        string $description,
        float $timeout = 5.0,
        float $interval = 0.05,
    ): void {
        $deadline = hrtime(true) + (int) ($timeout * 1_000_000_000);

        while (!$condition()) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException("Timed out after {$timeout}s waiting for $description");
            }

            delay($interval);
        }
    }
}
