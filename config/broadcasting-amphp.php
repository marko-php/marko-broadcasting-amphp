<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Interface and port `marko broadcasting:serve` listens on (overridable with --host / --port).
    'host' => Env::string('BROADCASTING_AMPHP_HOST', '0.0.0.0'),
    'port' => Env::int('BROADCASTING_AMPHP_PORT', 8085, min: 1, max: 65535),
    // Path browsers open with EventSource, e.g. /stream?channels=orders.42,private-users.7&token=...
    'path' => Env::string('BROADCASTING_AMPHP_PATH', '/stream'),
    // JSON health check with aggregate counts (open streams, active channels); no channel names.
    'health_path' => Env::string('BROADCASTING_AMPHP_HEALTH_PATH', '/health'),
    // Adds per-channel counts (channel names included) to /health for requests that send the
    // health_secret in an X-Health-Secret header. Requires health_secret.
    'health_detail' => Env::bool('BROADCASTING_AMPHP_HEALTH_DETAIL', false),
    // Shared secret that unlocks per-channel health detail. Required when health_detail is true.
    'health_secret' => Env::string('BROADCASTING_AMPHP_HEALTH_SECRET', ''),
    // Base URL browsers reach the server on (used by AmphpSubscriberToken::streamUrl()).
    'public_url' => Env::string('BROADCASTING_AMPHP_PUBLIC_URL', 'http://localhost:8085'),
    // Prepended to every channel name to form the pub/sub channel.
    'channel_prefix' => Env::string('BROADCASTING_AMPHP_CHANNEL_PREFIX', 'broadcast.'),
    // Secret used to sign subscriber tokens for private channels. Required for private channels.
    'app_key' => Env::string('BROADCASTING_AMPHP_APP_KEY', ''),
    // Seconds between `:` heartbeat comments on every open stream.
    'heartbeat' => Env::int('BROADCASTING_AMPHP_HEARTBEAT', 15, min: 0),
    // Events kept per channel for Last-Event-ID replay (per process).
    'replay_buffer' => Env::int('BROADCASTING_AMPHP_REPLAY_BUFFER', 100, min: 0),
    // Seconds an event stays replayable.
    'replay_ttl' => Env::int('BROADCASTING_AMPHP_REPLAY_TTL', 300, min: 0),
    // Open streams allowed per process; further requests get 503 with Retry-After.
    'max_connections' => Env::int('BROADCASTING_AMPHP_MAX_CONNECTIONS', 10000, min: 0),
    // Open streams allowed per client IP (0 disables the per-IP limit).
    'max_connections_per_ip' => Env::int('BROADCASTING_AMPHP_MAX_CONNECTIONS_PER_IP', 100, min: 0),
    // Origins allowed to open streams cross-origin; '*' allows any origin.
    'allowed_origins' => Env::list('BROADCASTING_AMPHP_ALLOWED_ORIGINS', ['*']),
    // Proxy addresses (CIDR allowed) whose X-Forwarded-For header is trusted for the client IP.
    'trusted_proxies' => Env::list('BROADCASTING_AMPHP_TRUSTED_PROXIES', []),
    // Lifetime of subscriber tokens, in seconds.
    'token_ttl' => Env::int('BROADCASTING_AMPHP_TOKEN_TTL', 3600, min: 0),
    // Seconds between connection-count log lines (0 disables them).
    'log_interval' => Env::int('BROADCASTING_AMPHP_LOG_INTERVAL', 60, min: 0),
];
