<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Auth\AmphpTokenClaims;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;

function amphpSignature(string $appKey = 'app-secret'): AmphpSignature
{
    return new AmphpSignature(new AmphpBroadcastingConfig(appKey: $appKey));
}

describe('AmphpSignature', function (): void {
    it('verifies a token it signed', function (): void {
        $signature = amphpSignature();
        $expires = time() + 60;

        $claims = $signature->verify($signature->sign(7, ['private-orders.7', 'private-users.7'], $expires));

        expect($claims)->toBeInstanceOf(AmphpTokenClaims::class)
            ->and($claims->userId)->toBe('7')
            ->and($claims->channels)->toBe(['private-orders.7', 'private-users.7'])
            ->and($claims->expiresAt)->toBe($expires)
            ->and($claims->allows('private-orders.7'))->toBeTrue()
            ->and($claims->allows('private-orders.8'))->toBeFalse();
    });

    it('signs HMAC-SHA256 over user id, channels and expiry', function (): void {
        $token = amphpSignature()->sign('7', ['private-orders.7'], 2000000000);
        [, $mac] = explode('.', $token);

        $expected = rtrim(
            strtr(base64_encode(hash_hmac('sha256', '7|private-orders.7|2000000000', 'app-secret', true)), '+/', '-_'),
            '=',
        );

        expect($mac)->toBe($expected);
    });

    it('signs tokens for guests with an empty user id', function (): void {
        $signature = amphpSignature();

        $claims = $signature->verify($signature->sign(null, ['private-lobby'], time() + 60));

        expect($claims?->userId)->toBe('');
    });

    it('rejects a forged token', function (): void {
        $token = amphpSignature('other-secret')->sign(7, ['private-orders.7'], time() + 60);

        expect(amphpSignature()->verify($token))->toBeNull();
    });

    it('rejects a token whose channel list was tampered with', function (): void {
        $signature = amphpSignature();
        [, $mac] = explode('.', $signature->sign(7, ['private-orders.7'], time() + 60));
        $payload = rtrim(
            strtr(
                base64_encode(json_encode(['u' => '7', 'c' => ['private-orders.8'], 'e' => time() + 60])),
                '+/',
                '-_',
            ),
            '=',
        );

        expect($signature->verify($payload . '.' . $mac))->toBeNull();
    });

    it('rejects an expired token', function (): void {
        $signature = amphpSignature();

        expect($signature->verify($signature->sign(7, ['private-orders.7'], time() - 1)))->toBeNull();
    });

    it('rejects malformed tokens', function (string $token): void {
        expect(amphpSignature()->verify($token))->toBeNull();
    })->with(['', 'abc', 'a.b.c', '!!!.???', 'e30.abc']);

    it('throws when the app key is empty', function (): void {
        amphpSignature('')->sign(7, ['private-orders.7'], time() + 60);
    })->throws(AmphpBroadcastException::class, 'No amphp broadcasting app key is configured.');
});
