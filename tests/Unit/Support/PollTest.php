<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\Tests\Support\Poll;
use Revolt\EventLoop;

describe('Poll', function (): void {
    it('returns as soon as the condition holds', function (): void {
        $calls = 0;

        Poll::until(function () use (&$calls): bool {
            $calls++;

            return true;
        }, 'an always-true condition');

        expect($calls)->toBe(1);
    });

    it('keeps polling until the condition becomes true', function (): void {
        $calls = 0;

        Poll::until(function () use (&$calls): bool {
            $calls++;

            return $calls === 3;
        }, 'the third call', interval: 0.001);

        expect($calls)->toBe(3);
    });

    it('throws naming the condition and timeout when the condition never holds', function (): void {
        expect(fn () => Poll::until(fn (): bool => false, 'the server to log stats', timeout: 0.2))
            ->toThrow(RuntimeException::class, 'Timed out after 0.2s waiting for the server to log stats');
    });

    it('lets event loop callbacks run between polls', function (): void {
        $fired = false;
        EventLoop::defer(function () use (&$fired): void {
            $fired = true;
        });

        Poll::until(function () use (&$fired): bool {
            return $fired;
        }, 'the deferred callback');

        expect($fired)->toBeTrue();
    });
});
