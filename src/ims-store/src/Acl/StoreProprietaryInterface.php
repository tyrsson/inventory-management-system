<?php

declare(strict_types=1);

namespace Ims\Store\Acl;

/**
 * Mirrors Laminas\Permissions\Acl\ProprietaryInterface for store-scoped resources
 * and roles. Implement on any object — resource or role — that belongs to a
 * specific store so that StoreOwnedResourceAssertion can compare both sides.
 */
interface StoreProprietaryInterface
{
    public function getStoreId(): int;
}
