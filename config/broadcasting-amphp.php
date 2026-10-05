<?php

declare(strict_types=1);

return [
    // Interface and port `marko broadcasting:serve` listens on (overridable with --host / --port).
    'host' => $_ENV['BROADCASTING_AMPHP_HOST'] ?? '0.0.0.0',
    'port' => (int) ($_ENV['BROADCASTING_AMPHP_PORT'] ?? 8085),
    // Path browsers open with EventSource, e.g. /stream?channels=orders.42,private-users.7&token=...
    'path' => $_ENV['BROADCASTING_AMPHP_PATH'] ?? '/stream',
    // JSON connection counts per channel; restrict it at your proxy if channel names are sensitive.
    'health_path' => $_ENV['BROADCASTING_AMPHP_HEALTH_PATH'] ?? '/health',
    // Base URL browsers reach the server on (used by AmphpSubscriberToken::streamUrl()).
    'public_url' => $_ENV['BROADCASTING_AMPHP_PUBLIC_URL'] ?? 'http://localhost:8085',
    // Prepended to every channel name to form the pub/sub channel.
    'channel_prefix' => $_ENV['BROADCASTING_AMPHP_CHANNEL_PREFIX'] ?? 'broadcast.',
    // Secret used to sign subscriber tokens for private channels. Required for private channels.
    'app_key' => $_ENV['BROADCASTING_AMPHP_APP_KEY'] ?? '',
    // Seconds between `:` heartbeat comments on every open stream.
    'heartbeat' => (int) ($_ENV['BROADCASTING_AMPHP_HEARTBEAT'] ?? 15),
    // Events kept per channel for Last-Event-ID replay (per process).
    'replay_buffer' => (int) ($_ENV['BROADCASTING_AMPHP_REPLAY_BUFFER'] ?? 100),
    // Seconds an event stays replayable.
    'replay_ttl' => (int) ($_ENV['BROADCASTING_AMPHP_REPLAY_TTL'] ?? 300),
    // Open streams allowed per process; further requests get 503 with Retry-After.
    'max_connections' => (int) ($_ENV['BROADCASTING_AMPHP_MAX_CONNECTIONS'] ?? 10000),
    // Open streams allowed per client IP (0 disables the per-IP limit).
    'max_connections_per_ip' => (int) ($_ENV['BROADCASTING_AMPHP_MAX_CONNECTIONS_PER_IP'] ?? 100),
    // Origins allowed to open streams cross-origin; '*' allows any origin.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', $_ENV['BROADCASTING_AMPHP_ALLOWED_ORIGINS'] ?? '*'),
    ))),
    // Proxy addresses (CIDR allowed) whose X-Forwarded-For header is trusted for the client IP.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', $_ENV['BROADCASTING_AMPHP_TRUSTED_PROXIES'] ?? ''),
    ))),
    // Lifetime of subscriber tokens, in seconds.
    'token_ttl' => (int) ($_ENV['BROADCASTING_AMPHP_TOKEN_TTL'] ?? 3600),
    // Seconds between connection-count log lines (0 disables them).
    'log_interval' => (int) ($_ENV['BROADCASTING_AMPHP_LOG_INTERVAL'] ?? 60),
];
