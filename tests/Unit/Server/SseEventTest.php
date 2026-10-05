<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\Server\SseEvent;
use Marko\Broadcasting\Amphp\Server\SseFrame;

describe('SseEvent', function (): void {
    it('formats id, event and data lines', function (): void {
        $event = new SseEvent(
            sequence: 3,
            channel: 'shows.42',
            id: 'evt-1',
            event: 'seat.sold',
            data: '{"seat":"A1"}',
            receivedAt: 100.0,
        );

        expect($event->toFrame())->toBe("id: evt-1\nevent: seat.sold\ndata: {\"seat\":\"A1\"}\n\n");
    });
});

describe('SseFrame', function (): void {
    it('formats heartbeat, reset and reconnect frames', function (): void {
        expect(SseFrame::HEARTBEAT)->toBe(":\n\n")
            ->and(SseFrame::reset('unknown-last-event-id'))->toBe(
                "event: reset\ndata: {\"reason\":\"unknown-last-event-id\"}\n\n",
            )
            ->and(SseFrame::reconnect(1000))->toBe("retry: 1000\nevent: reconnect\ndata: {}\n\n");
    });
});
