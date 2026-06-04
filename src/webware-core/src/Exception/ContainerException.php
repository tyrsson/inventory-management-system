<?php

declare(strict_types=1);

namespace Webware\Core\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

use function sprintf;

final class ContainerException extends RuntimeException implements ExceptionInterface, ContainerExceptionInterface
{
    public static function forMissingConfigKey(string $key, string $currentFactory): static
    {
        return new self(
            sprintf(
                'Missing required config key: %s in factory: %s',
                $key,
                $currentFactory
            )
        );
    }

    public static function forEmptyConfiguration(string $key, string $currentFactory): static
    {
        return new self(
            sprintf(
                'Configuration key "%s" is present but empty in factory: %s',
                $key,
                $currentFactory
            )
        );
    }

    public static function forMissingConfigService(string $serviceName, string $currentFactory): static
    {
        return new self(
            sprintf(
                'The "%s" service was not found in the container. Requested by factory: %s',
                $serviceName,
                $currentFactory
            )
        );
    }

    public static function forInvalidConfigType(
        string $key,
        string $expectedType,
        string $receivedType,
        string $currentFactory,
    ): static {
        return new self(
            sprintf(
                'Configuration key "%s" is expected to be of type "%s" in factory: %s received type "%s"',
                $key,
                $expectedType,
                $receivedType,
                $currentFactory
            )
        );
    }
}
