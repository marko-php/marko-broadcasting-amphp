<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Auth\AmphpTokenClaims;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Testing\Fake\FakeClock;

/**
 * Tokens are checked against a FakeClock frozen at 2026-01-01 12:00:00 UTC (1767268800).
 */
function amphpSignature(
    string $appKey = 'app-secret',
    ?FakeClock $clock = null,
): AmphpSignature {
    return new AmphpSignature(
        new AmphpBroadcastingConfig(appKey: $appKey),
        $clock ?? new FakeClock('@1767268800'),
    );
}

describe('AmphpSignature', function (): void {
    it('accepts a token until its expiry second on the injected clock and rejects it after', function (): void {
        $clock = new FakeClock('@1767268800');
        $signature = amphpSignature(clock: $clock);
        $token = $signature->sign(7, ['private-orders.7'], 1767268800 + 60);

        $clock->travel('+60 seconds');
        $atExpiry = $signature->verify($token);

        $clock->travel('+1 second');

        expect($atExpiry)->toBeInstanceOf(AmphpTokenClaims::class)
            ->and($signature->verify($token))->toBeNull();
    });

    it('verifies a token it signed', function (): void {
        $signature = amphpSignature();
        $expires = 1767268800 + 60;

        $claims = $signature->verify($signature->sign(7, ['private-orders.7', 'private-users.7'], $expires));

        expect($claims)->toBeInstanceOf(AmphpTokenClaims::class)
            ->and($claims->userId)->toBe('7')
            ->and($claims->channels)->toBe(['private-orders.7', 'private-users.7'])
            ->and($claims->expiresAt)->toBe($expires)
            ->and($claims->allows('private-orders.7'))->toBeTrue()
            ->and($claims->allows('private-orders.8'))->toBeFalse();
    });

    it('signs HMAC-SHA256 over the exact encoded payload segment', function (): void {
        $token = amphpSignature()->sign('7', ['private-orders.7'], 2000000000);
        [$payload, $mac] = explode('.', $token);

        $expected = rtrim(
            strtr(base64_encode(hash_hmac('sha256', $payload, 'app-secret', true)), '+/', '-_'),
            '=',
        );

        expect($mac)->toBe($expected);
    });

    it('round-trips a valid token through sign and verify', function (): void {
        $signature = amphpSignature();
        $expires = 1767268800 + 60;

        $claims = $signature->verify($signature->sign('1', ['private-user.1', 'private-admin'], $expires));

        expect($claims?->userId)->toBe('1')
            ->and($claims?->channels)->toBe(['private-user.1', 'private-admin'])
            ->and($claims?->expiresAt)->toBe($expires);
    });

    it('rejects a token whose comma-joined channel was re-split into separate channels', function (): void {
        $signature = amphpSignature();
        $expires = 1767268800 + 60;
        [, $mac] = explode('.', $signature->sign('1', ['private-user.1,private-admin'], $expires));
        $resplit = rtrim(
            strtr(
                base64_encode(json_encode(['u' => '1', 'c' => ['private-user.1', 'private-admin'], 'e' => $expires])),
                '+/',
                '-_',
            ),
            '=',
        );

        expect($signature->verify($resplit . '.' . $mac))->toBeNull();
    });

    it('rejects a token with a single tampered payload byte', function (): void {
        $signature = amphpSignature();
        [$payload, $mac] = explode('.', $signature->sign(7, ['private-orders.7'], 1767268800 + 60));
        $tampered = $payload;
        $tampered[5] = $tampered[5] === 'A' ? 'B' : 'A';

        expect($signature->verify($tampered . '.' . $mac))->toBeNull();
    });

    it('signs tokens for guests with an empty user id', function (): void {
        $signature = amphpSignature();

        $claims = $signature->verify($signature->sign(null, ['private-lobby'], 1767268800 + 60));

        expect($claims?->userId)->toBe('');
    });

    it('rejects a forged token', function (): void {
        $token = amphpSignature('other-secret')->sign(7, ['private-orders.7'], 1767268800 + 60);

        expect(amphpSignature()->verify($token))->toBeNull();
    });

    it('rejects a token whose channel list was tampered with', function (): void {
        $signature = amphpSignature();
        [, $mac] = explode('.', $signature->sign(7, ['private-orders.7'], 1767268800 + 60));
        $payload = rtrim(
            strtr(
                base64_encode(json_encode(['u' => '7', 'c' => ['private-orders.8'], 'e' => 1767268800 + 60])),
                '+/',
                '-_',
            ),
            '=',
        );

        expect($signature->verify($payload . '.' . $mac))->toBeNull();
    });

    it('rejects an expired token', function (): void {
        $signature = amphpSignature();

        expect($signature->verify($signature->sign(7, ['private-orders.7'], 1767268800 - 1)))->toBeNull();
    });

    it('rejects malformed tokens', function (string $token): void {
        expect(amphpSignature()->verify($token))->toBeNull();
    })->with(['', 'abc', 'a.b.c', '!!!.???', 'e30.abc']);

    it('throws when the app key is empty', function (): void {
        amphpSignature('')->sign(7, ['private-orders.7'], 1767268800 + 60);
    })->throws(AmphpBroadcastException::class, 'No amphp broadcasting app key is configured.');
});
