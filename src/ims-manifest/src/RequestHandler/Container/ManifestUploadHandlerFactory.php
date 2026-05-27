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

use Ims\Manifest\RequestHandler\ManifestUploadHandler;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

final class ManifestUploadHandlerFactory
{
    public function __invoke(ContainerInterface $container): ManifestUploadHandler
    {
        return new ManifestUploadHandler(
            $container->get(TemplateRendererInterface::class),
        );
    }
}

