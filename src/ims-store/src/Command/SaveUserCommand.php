<?php

declare(strict_types=1);

/**
 * This file is part of the Ims Store package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Store\Command;

use Ims\Store\Entity\User;
use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;
use Webware\UserManager\UserInterface;

final readonly class SaveUserCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public int $storeId,
    ) {}
}
