<?php

declare(strict_types=1);

namespace Ims\Manifest\View\Helper;

use Ims\Manifest\Container\Configuration;
use Mezzio\Helper\UrlHelper;
use Psr\Container\ContainerInterface;

final readonly class ManifestUrlFactory
{
    public function __invoke(ContainerInterface $container): ManifestUrl
    {
        return new ManifestUrl(
            urlHelper: $container->get(UrlHelper::class),
            routeNamePrefix: Configuration::getRouteNamePrefix($container, self::class),
        );
    }
}
