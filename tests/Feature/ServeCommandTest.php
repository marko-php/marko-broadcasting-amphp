<?php

declare(strict_types=1);

use Marko\Amphp\AmphpConfig;
use Marko\Amphp\EventLoopRunner;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Command\ServeCommand;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Server\AmphpSseServer;
use Marko\Broadcasting\Amphp\Tests\Support\InMemoryPubSub;
use Marko\Broadcasting\Amphp\Tests\Support\SseTestClient;
use Marko\Clock\SystemClock;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeLogger;

/**
 * Runs the real event loop and delivers a signal from inside it once a client is streaming.
 */
class SignallingEventLoopRunner extends EventLoopRunner
{
    /**
     * @var array<int, callable>
     */
    public array $signals = [];

    public ?SseTestClient $client = null;

    public ?string $streamed = null;

    public function __construct(
        private readonly AmphpSseServer $amphpSseServer,
        private readonly int $deliver,
    ) {}

    public function onSignal(
        int $signal,
        callable $handler,
    ): void {
        $this->signals[$signal] = $handler;
    }

    protected function doRun(): void
    {
        $this->client = SseTestClient::get((int) $this->amphpSseServer->port(), '/stream?channels=shows.42');
        $this->client->waitFor(":ok\n\n");
        ($this->signals[$this->deliver])();
        $this->streamed = $this->client->waitForEnd();
    }

    protected function doStop(): void {}
}

function serveCommandServer(): AmphpSseServer
{
    $config = new AmphpBroadcastingConfig(host: '0.0.0.0', port: 1, heartbeat: 30, logInterval: 0);

    $clock = new SystemClock();

    return new AmphpSseServer(
        $config,
        new InMemoryPubSub(),
        new AmphpSignature($config, $clock),
        new FakeLogger(),
        $clock,
    );
}

/**
 * @return array{stream: resource, output: Output}
 */
function serveCommandOutput(): array
{
    $stream = fopen('php://memory', 'r+');

    return ['stream' => $stream, 'output' => new Output($stream)];
}

function serveAmphpConfig(): AmphpConfig
{
    return new AmphpConfig(new FakeConfigRepository([
        'amphp.shutdown_timeout' => 2,
        'amphp.channels' => [],
    ]));
}

describe('ServeCommand', function (): void {
    it('registers the broadcasting:serve command', function (): void {
        $attribute = new ReflectionClass(ServeCommand::class)->getAttributes(Command::class)[0]->newInstance();

        expect($attribute->name)->toBe('broadcasting:serve')
            ->and($attribute->description)->not->toBeEmpty();
    });

    it('stops on SIGINT and SIGTERM', function (int $signal): void {
        $server = serveCommandServer();
        $runner = new SignallingEventLoopRunner($server, $signal);
        ['stream' => $stream, 'output' => $output] = serveCommandOutput();

        $exitCode = new ServeCommand($runner, serveAmphpConfig(), $server)
            ->execute(new Input(['marko', 'broadcasting:serve', '--host=127.0.0.1', '--port=0']), $output);

        rewind($stream);

        expect($exitCode)->toBe(0)
            ->and(array_keys($runner->signals))->toBe([SIGINT, SIGTERM])
            ->and($runner->streamed)->toEndWith("event: reconnect\ndata: {}\n\n")
            ->and($server->isRunning())->toBeFalse()
            ->and(stream_get_contents($stream))->toContain('Broadcasting server listening on 127.0.0.1:')
            ->toContain('Stopping broadcasting server...')
            ->toContain('Broadcasting server stopped.');
    })->with(['SIGINT' => SIGINT, 'SIGTERM' => SIGTERM]);

    it('honours --host and --port options', function (): void {
        $server = serveCommandServer();
        $runner = new SignallingEventLoopRunner($server, SIGTERM);
        ['output' => $output] = serveCommandOutput();

        new ServeCommand($runner, serveAmphpConfig(), $server)
            ->execute(new Input(['marko', 'broadcasting:serve', '--host=127.0.0.1', '--port=0']), $output);

        expect($runner->client)->not->toBeNull();
    });

    it('rejects an invalid --port', function (): void {
        $server = serveCommandServer();
        ['output' => $output] = serveCommandOutput();

        new ServeCommand(new SignallingEventLoopRunner($server, SIGTERM), serveAmphpConfig(), $server)
            ->execute(new Input(['marko', 'broadcasting:serve', '--port=http']), $output);
    })->throws(AmphpBroadcastException::class, "Invalid --port value 'http'.");
});
