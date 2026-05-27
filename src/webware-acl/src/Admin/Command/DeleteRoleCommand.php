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

namespace Webware\Acl\Admin\Command;

use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;

final readonly class DeleteRoleCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    public function __construct(
        public int $rolePk,
    ) {}
}
