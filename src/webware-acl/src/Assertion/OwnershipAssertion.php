<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Assertion;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;

/**
 * Fail-closed ownership assertion.
 *
 * Unlike Laminas\Permissions\Acl\Assertion\OwnershipAssertion, this implementation
 * denies access (returns false) whenever the ownership check cannot be completed:
 *
 *   - Either side does not implement ProprietaryInterface → DENY
 *   - The resource has no owner (getOwnerId() === null)    → DENY
 *
 * This prevents unintended access grants when objects are passed that do not
 * carry ownership information.
 */
final class OwnershipAssertion implements AssertionInterface
{
    #[Override]
    public function assert(
        Acl $acl,
        ?RoleInterface $role = null,
        ?ResourceInterface $resource = null,
        $privilege = null,
    ): bool {
        if (! $role instanceof ProprietaryInterface || ! $resource instanceof ProprietaryInterface) {
            return false;
        }

        if ($resource->getOwnerId() === null) {
            return false;
        }

        return $resource->getOwnerId() === $role->getOwnerId();
    }
}
