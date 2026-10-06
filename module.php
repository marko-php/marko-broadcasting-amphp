<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        BroadcasterInterface::class => AmphpBroadcaster::class,
        AmphpBroadcastingConfig::class => static function (ContainerInterface $container): AmphpBroadcastingConfig {
            $config = $container->get(ConfigRepositoryInterface::class);

            return new AmphpBroadcastingConfig(
                host: $config->getString(key: 'broadcasting-amphp.host'),
                port: $config->getInt(key: 'broadcasting-amphp.port'),
                path: $config->getString(key: 'broadcasting-amphp.path'),
                healthPath: $config->getString(key: 'broadcasting-amphp.health_path'),
                healthDetail: $config->getBool(key: 'broadcasting-amphp.health_detail'),
                healthSecret: $config->getString(key: 'broadcasting-amphp.health_secret'),
                publicUrl: $config->getString(key: 'broadcasting-amphp.public_url'),
                channelPrefix: $config->getString(key: 'broadcasting-amphp.channel_prefix'),
                appKey: $config->getString(key: 'broadcasting-amphp.app_key'),
                heartbeat: $config->getInt(key: 'broadcasting-amphp.heartbeat'),
                replayBuffer: $config->getInt(key: 'broadcasting-amphp.replay_buffer'),
                replayTtl: $config->getInt(key: 'broadcasting-amphp.replay_ttl'),
                maxConnections: $config->getInt(key: 'broadcasting-amphp.max_connections'),
                maxConnectionsPerIp: $config->getInt(key: 'broadcasting-amphp.max_connections_per_ip'),
                allowedOrigins: array_values(array_map(
                    strval(...),
                    $config->getArray(key: 'broadcasting-amphp.allowed_origins'),
                )),
                trustedProxies: array_values(array_filter(
                    array_map(strval(...), $config->getArray(key: 'broadcasting-amphp.trusted_proxies')),
                    fn (string $proxy): bool => $proxy !== '',
                )),
                tokenTtl: $config->getInt(key: 'broadcasting-amphp.token_ttl'),
                logInterval: $config->getInt(key: 'broadcasting-amphp.log_interval'),
            );
        },
    ],
    'singletons' => [
        AmphpBroadcastingConfig::class,
    ],
];
