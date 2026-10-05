<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

/**
 * One event received from pub/sub. The sequence is local to this server process and orders
 * events across channels for Last-Event-ID replay; the id is the publisher's event id.
 */
readonly class SseEvent
{
    public function __construct(
        public int $sequence,
        public string $channel,
        public string $id,
        public string $event,
        public string $data,
        public float $receivedAt,
    ) {}

    public function toFrame(): string
    {
        return "id: $this->id\nevent: $this->event\ndata: $this->data\n\n";
    }
}
