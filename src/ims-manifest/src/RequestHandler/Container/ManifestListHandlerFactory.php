<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Manifest\RequestHandler\Container;

use Ims\Manifest\Repository\ManifestRepositoryInterface;
use Ims\Manifest\RequestHandler\ManifestListHandler;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

final class ManifestListHandlerFactory
{
    public function __invoke(ContainerInterface $container): ManifestListHandler
    {
        return new ManifestListHandler(
            template: $container->get(TemplateRendererInterface::class),
            manifests: $container->get(ManifestRepositoryInterface::class),
        );
    }
}
