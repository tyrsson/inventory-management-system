<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Command;

use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;

final readonly class ProtectRouteCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    /**
     * @param string   $routeName      Route name (resource_id)
     * @param string   $grantMode      'explicit' | 'inherited'
     * @param string   $ruleType       'allow' | 'deny'
     * @param string   $roleId         role_id string
     * @param string[] $privileges     Privilege names e.g. ['read', 'create']
     * @param string   $assertionFqcn  FQCN of assertion class, or empty string
     * @param string   $assertionMode  'none' | 'must' | 'may'
     */
    public function __construct(
        public string $routeName,
        public string $grantMode,
        public string $ruleType,
        public string $roleId,
        public array  $privileges,
        public string $assertionFqcn = '',
        public string $assertionMode = 'none',
    ) {}
}
