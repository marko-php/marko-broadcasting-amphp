<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Auth;

use JsonException;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Psr\Clock\ClockInterface;

/**
 * Signs and verifies subscriber tokens: base64url(JSON {u, c, e}) . "." . base64url(MAC), where
 * MAC = HMAC-SHA256(app_key, "user_id|channel,channel|expires").
 *
 * The server verifies tokens with the shared app key alone, without calling back into the app.
 */
readonly class AmphpSignature
{
    public function __construct(
        private AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * @param list<string> $channels
     * @throws AmphpBroadcastException|BroadcastException
     */
    public function sign(
        int|string|null $userId,
        array $channels,
        int $expiresAt,
    ): string {
        if ($this->amphpBroadcastingConfig->appKey === '') {
            throw AmphpBroadcastException::missingAppKey();
        }

        $userId = (string) $userId;

        try {
            $payload = json_encode(
                ['u' => $userId, 'c' => $channels, 'e' => $expiresAt],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $e) {
            throw BroadcastException::unencodablePayload('subscriber token', $e->getMessage(), $e);
        }

        return $this->base64UrlEncode($payload) . '.' . $this->mac($userId, $channels, $expiresAt);
    }

    /**
     * Returns the claims of a genuine, unexpired token, or null for anything else.
     */
    public function verify(string $token): ?AmphpTokenClaims
    {
        if ($this->amphpBroadcastingConfig->appKey === '') {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        $payload = $this->base64UrlDecode($parts[0]);

        if ($payload === null) {
            return null;
        }

        try {
            $claims = json_decode($payload, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($claims)
            || !is_string($claims['u'] ?? null)
            || !is_int($claims['e'] ?? null)
            || !is_array($claims['c'] ?? null)
            || !array_is_list($claims['c'])
            || !array_all($claims['c'], fn (mixed $channel): bool => is_string($channel))
        ) {
            return null;
        }

        /** @var list<string> $channels */
        $channels = $claims['c'];

        if (!hash_equals($this->mac($claims['u'], $channels, $claims['e']), $parts[1])) {
            return null;
        }

        if ($claims['e'] < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return new AmphpTokenClaims($claims['u'], $channels, $claims['e']);
    }

    /**
     * @param list<string> $channels
     */
    private function mac(
        string $userId,
        array $channels,
        int $expiresAt,
    ): string {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            $userId . '|' . implode(',', $channels) . '|' . $expiresAt,
            $this->amphpBroadcastingConfig->appKey,
            true,
        ));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
