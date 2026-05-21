<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\AclInterface as LaminasAclInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Webware\UserManager\UserInterface;

interface AclInterface
{
    public function getAcl(): LaminasAclInterface;

    public function isAllowed(
        UserInterface|null $user = null,
        string|ResourceInterface|null $resource = null,
        ?string $privilege = null,
    ): bool;

    /**
     * Checks whether the authenticated user may access the matched route resource.
     *
     * $user must be passed as the UserInterface aggregate object — NOT decomposed
     * into string role names via getRoles(). Passing the object directly ensures
     * any AssertionInterface (e.g. OwnershipAssertion) receives a role that
     * implements ProprietaryInterface, which string role names do not satisfy.
     *
     * UserInterface extends RoleInterface, so Laminas ACL resolves it via
     * getRoleId() in the role registry. Multi-role support (from the bound
     * Mezzio\Authentication\UserInterface contract) is intentionally not used
     * here — Laminas ACL handles multi-role via role inheritance, not arrays.
     *
     * Pass null for unauthenticated / guest requests.
     *
     * FAIL CLOSED — routes not registered as ACL resources are always denied.
     */
    public function isAllowedRoute(
        UserInterface|null $user,
        ResourceInterface $resource,
    ): bool;
}
