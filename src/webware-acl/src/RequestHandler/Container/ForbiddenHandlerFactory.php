<?php

declare(strict_types=1);

namespace Webware\Acl\RequestHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\RequestHandler\ForbiddenHandler;

/**
 * Creates the default ForbiddenHandler.
 *
 * To substitute a custom denial handler, bind your own class to
 * ForbiddenHandlerInterface in your application's DI config:
 *
 * ```php
 * // config/autoload/acl.local.php
 * use Webware\Acl\RequestHandler\ForbiddenHandlerInterface;
 *
 * return [
 *     'dependencies' => [
 *         'aliases' => [
 *             ForbiddenHandlerInterface::class => MyCustomForbiddenHandler::class,
 *         ],
 *     ],
 * ];
 * ```
 */
final class ForbiddenHandlerFactory
{
    public function __invoke(ContainerInterface $container): ForbiddenHandler
    {
        $config = $container->get('config');
        $acl    = $config[AclInterface::class] ?? [];

        return new ForbiddenHandler(
            loginPath:         (string) ($acl['login_path']         ?? '/login'),
            forbiddenRedirect: ($acl['forbidden_redirect'] ?? '/') === '' ? null : ($acl['forbidden_redirect'] ?? '/'),
            forbiddenTemplate: ($acl['forbidden_template'] ?? null) ?: null,
        );
    }
}
