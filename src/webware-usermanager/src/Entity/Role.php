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

namespace Webware\UserManager\Entity;

/**
 * @deprecated Roles are now config-driven with no DB table. Use \Webware\Acl\Entity\Role instead.
 */
final readonly class Role
{
    public function __construct(
        public int $id,
        public string $roleId,
    ) {}
}
