<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\Log\PsrLoggerBridge;
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeLogger;
use Psr\Log\InvalidArgumentException;

describe('PsrLoggerBridge', function (): void {
    it('forwards http-server log records to the Marko logger', function (): void {
        $logger = new FakeLogger();
        $bridge = new PsrLoggerBridge($logger);

        $bridge->notice('Listening on {address}', ['address' => '127.0.0.1:8085']);
        $bridge->error('Client failed');

        expect($logger->entries)->toBe([
            ['level' => LogLevel::Notice, 'message' => 'Listening on {address}', 'context' => ['address' => '127.0.0.1:8085']],
            ['level' => LogLevel::Error, 'message' => 'Client failed', 'context' => []],
        ]);
    });

    it('throws for unknown PSR-3 levels', function (): void {
        new PsrLoggerBridge(new FakeLogger())->log('loud', 'x');
    })->throws(InvalidArgumentException::class, 'Unknown PSR-3 log level');
});
