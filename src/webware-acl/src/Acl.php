<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\AclInterface as LaminasAclInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Override;
use Webware\Acl\Role\UserRoleIterator;
use Webware\UserManager\UserInterface;

final class Acl implements AclInterface
{
    public function __construct(
        private readonly LaminasAclInterface $acl,
    ) {}

    public function getAcl(): LaminasAclInterface
    {
        return $this->acl;
    }

    #[Override]
    public function isAllowed(
        UserInterface|null $user = null,
        string|ResourceInterface|null $resource = null,
        ?string $privilege = null
    ): bool {
        if ($user === null) {
            return false;
        }

        foreach (new UserRoleIterator($user) as $roleProxy) {
            if ($this->acl->isAllowed($roleProxy, $resource, $privilege)) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    public function isAllowedRoute(
        UserInterface|null $user,
        ResourceInterface $resource,
    ): bool {
        // FAIL CLOSED — intentional, do not change to true.
        // Routes must be explicitly registered as ACL resources to be accessible.
        // This is a hard requirement; unregistered routes are always denied.
        if (! $this->acl->hasResource($resource->getResourceId())) {
            return false;
        }

        return $this->isAllowed($user, $resource, null);
    }

}
