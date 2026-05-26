<?php

declare(strict_types=1);


namespace Ims\Store\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;

final class StoreOwnedResourceAssertion implements AssertionInterface
{
    #[Override]
    public function assert(
        Acl $acl,
        ?RoleInterface $role = null,
        ?ResourceInterface $resource = null,
        $privilege = null,
    ): bool {
        if (! $resource instanceof StoreProprietaryInterface) {
            return false;
        }

        if (! $role instanceof StoreProprietaryInterface) {
            return false;
        }

        return $resource->getStoreId() === $role->getStoreId();
    }
}
