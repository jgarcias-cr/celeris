<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;

final class RealtimeGatewayServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            RealtimeGatewayClientInterface::class,
            static fn (ContainerInterface $container): RealtimeGatewayClientInterface => self::buildClient($container),
            [ConfigRepository::class],
        );

        $services->singleton(
            HttpRealtimeGatewayClient::class,
            static fn (ContainerInterface $container): HttpRealtimeGatewayClient => self::buildHttpClient($container),
            [ConfigRepository::class],
        );
    }

    private static function buildClient(ContainerInterface $container): RealtimeGatewayClientInterface
    {
        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            return new NullRealtimeGatewayClient();
        }

        if (!self::toBool($config->get('notifications.realtime.enabled', false))) {
            return new NullRealtimeGatewayClient();
        }

        return self::buildHttpClient($container);
    }

    private static function buildHttpClient(ContainerInterface $container): HttpRealtimeGatewayClient
    {
        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            return new HttpRealtimeGatewayClient(endpoint: '');
        }

        return new HttpRealtimeGatewayClient(
            endpoint: (string) $config->get('notifications.realtime.endpoint', ''),
            timeoutSeconds: (int) $config->get('notifications.realtime.timeout_seconds', 5),
            serviceId: (string) $config->get('notifications.realtime.service_id', ''),
            serviceSecret: (string) $config->get('notifications.realtime.service_secret', ''),
        );
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed ?? false;
        }

        return false;
    }
}
