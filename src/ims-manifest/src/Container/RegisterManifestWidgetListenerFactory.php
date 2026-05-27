<?php

declare(strict_types=1);

namespace Ims\Manifest\Container;

use Ims\Manifest\Listener\RegisterManifestWidgetListener;
use Ims\Manifest\Repository\ManifestRepositoryInterface;
use Psr\Container\ContainerInterface;

final class RegisterManifestWidgetListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterManifestWidgetListener
    {
        return new RegisterManifestWidgetListener(
            $container->get(ManifestRepositoryInterface::class),
        );
    }
}
