<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Server\AmphpSseServer;
use Marko\Broadcasting\Amphp\Tests\Support\SseTestClient;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeLogger;

/**
 * Null when Redis is reachable, otherwise why the live test is skipped.
 */
function redisLiveSkipReason(): ?string
{
    $host = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST');

    if (!is_string($host) || $host === '') {
        return 'REDIS_HOST is not set. Start Redis with `docker compose -f tests/Integration/compose.yml up -d` and run with REDIS_HOST=127.0.0.1.';
    }

    $port = (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);
    $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.5);

    if ($socket === false) {
        return "Redis is not reachable at $host:$port ($errorMessage).";
    }

    fclose($socket);

    return null;
}

it('delivers events through pubsub-redis', function (): void {
    $reason = redisLiveSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    $connection = new RedisPubSubConnection(
        host: (string) ($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST')),
        port: (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379),
    );
    $pubSubConfig = new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'redis',
        'pubsub.prefix' => 'marko-test-' . bin2hex(random_bytes(4)) . ':',
    ]));
    $config = new AmphpBroadcastingConfig(host: '127.0.0.1', port: 0, logInterval: 0);
    $server = new AmphpSseServer(
        $config,
        new RedisSubscriber($connection, $pubSubConfig),
        new AmphpSignature($config),
        new FakeLogger(),
    );
    $server->start();

    try {
        $client = SseTestClient::get((int) $server->port(), '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");

        new AmphpBroadcaster(new RedisPublisher($connection, $pubSubConfig), $config)
            ->broadcast('shows.42', 'seat.sold', ['seat' => 'A1'], 'evt-redis');

        expect($client->waitFor('id: evt-redis'))->toContain("event: seat.sold\ndata: {\"seat\":\"A1\"}");
    } finally {
        $server->stop(1.0);
    }
});
