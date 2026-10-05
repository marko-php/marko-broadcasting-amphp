<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Subscriber\AmphpSubscriberToken;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\PrivateChannel;
use Marko\Testing\Fake\FakeAuthenticatable;

function amphpBroadcastingTestConfig(string $appKey = 'app-secret'): AmphpBroadcastingConfig
{
    return new AmphpBroadcastingConfig(
        publicUrl: 'https://realtime.example.com/',
        appKey: $appKey,
        tokenTtl: 600,
    );
}

function amphpSubscriberToken(string $appKey = 'app-secret'): AmphpSubscriberToken
{
    /** @noinspection PhpMissingParentConstructorInspection - Test stub replaces discovery-backed authorization */
    $channelRegistry = new class () extends ChannelRegistry
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}

        public function authorize(
            string $channelName,
            ?AuthenticatableInterface $user,
        ): bool {
            return match ($channelName) {
                'orders.7' => $user !== null,
                'orders.8' => false,
                default => throw ChannelAuthorizationException::unknownChannel($channelName),
            };
        }
    };

    $config = amphpBroadcastingTestConfig($appKey);

    return new AmphpSubscriberToken(
        amphpSignature: new AmphpSignature($config),
        amphpBroadcastingConfig: $config,
        channelRegistry: $channelRegistry,
    );
}

describe('AmphpSubscriberToken', function (): void {
    it('includes only authorized private channels', function (): void {
        $token = amphpSubscriberToken()->for(
            ['shows.42', new PrivateChannel('orders.7'), new PrivateChannel('orders.8')],
            new FakeAuthenticatable(id: 7),
        );

        $claims = new AmphpSignature(amphpBroadcastingTestConfig())->verify($token);

        expect($claims?->channels)->toBe(['private-orders.7'])
            ->and($claims?->userId)->toBe('7');
    });

    it('expires tokens after token_ttl seconds', function (): void {
        $token = amphpSubscriberToken()->for([new PrivateChannel('orders.7')], new FakeAuthenticatable(id: 7));

        $claims = new AmphpSignature(amphpBroadcastingTestConfig())->verify($token);

        expect($claims?->expiresAt)->toBeGreaterThanOrEqual(time() + 599)
            ->toBeLessThanOrEqual(time() + 600);
    });

    it('denies private channels to guests when the authorizer requires a user', function (): void {
        $token = amphpSubscriberToken()->for([new PrivateChannel('orders.7')], null);

        expect(new AmphpSignature(amphpBroadcastingTestConfig())->verify($token)?->channels)->toBe([]);
    });

    it('throws loudly for private channels without an authorizer', function (): void {
        amphpSubscriberToken()->for([new PrivateChannel('unknown.1')], null);
    })->throws(ChannelAuthorizationException::class);

    it('throws when the app key is empty', function (): void {
        amphpSubscriberToken('')->for([new PrivateChannel('orders.7')], new FakeAuthenticatable(id: 7));
    })->throws(AmphpBroadcastException::class, 'No amphp broadcasting app key is configured.');

    it('builds a stream url with channels and token', function (): void {
        $url = amphpSubscriberToken()->streamUrl(
            ['shows.42', new PrivateChannel('orders.7')],
            new FakeAuthenticatable(id: 7),
        );

        expect($url)->toStartWith('https://realtime.example.com/stream?channels=shows.42%2Cprivate-orders.7&token=');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect(new AmphpSignature(amphpBroadcastingTestConfig())->verify($query['token'])?->channels)
            ->toBe(['private-orders.7']);
    });

    it('builds a stream url without a token for public channels', function (): void {
        expect(amphpSubscriberToken('')->streamUrl(['shows.42', 'shows.43'], null))
            ->toBe('https://realtime.example.com/stream?channels=shows.42%2Cshows.43');
    });
});
