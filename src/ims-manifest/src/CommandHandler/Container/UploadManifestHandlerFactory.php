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

namespace Ims\Manifest\CommandHandler\Container;

use Ims\Manifest\CommandHandler\UploadManifestHandler;
use Ims\Manifest\Repository\ManifestRepositoryInterface;
use Psr\Container\ContainerInterface;

final class UploadManifestHandlerFactory
{
    public function __invoke(ContainerInterface $container): UploadManifestHandler
    {
        return new UploadManifestHandler(
            $container->get(ManifestRepositoryInterface::class),
        );
    }
}
