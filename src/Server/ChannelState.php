<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use Amp\DeferredFuture;
use Marko\PubSub\Subscription;

/**
 * Hub bookkeeping for one channel: its shared pub/sub subscription and the streams fed by it.
 */
class ChannelState
{
    public ?Subscription $subscription = null;

    /**
     * @var array<int, SseConnection>
     */
    public array $connections = [];

    /**
     * Joins currently waiting for the subscription; keeps the channel open while they wait.
     */
    public int $joining = 0;

    public bool $cancelled = false;

    /**
     * @var DeferredFuture<null>
     */
    public readonly DeferredFuture $ready;

    public function __construct(
        public readonly string $name,
    ) {
        $this->ready = new DeferredFuture();
    }
}
