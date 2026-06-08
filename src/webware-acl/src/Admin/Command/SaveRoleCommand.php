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

final readonly class SaveRoleCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    public function __construct(
        /** 
         * The unique identifier of the role.
         *
         * @var int|null
         */
        public ?int $id,
        /** 
         * The role identifier.
         *
         * @var string
         */
        public string $roleId,
        /** 
         * The parent role identifiers.
         *
         * @var string[]|null
         */
        public ?array $parentId = null,
    ) {}
}
