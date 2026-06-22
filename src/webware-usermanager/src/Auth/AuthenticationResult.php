<?php

declare(strict_types=1);

/**
 * This file is part of the Webware UserManager package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Auth;

use Webware\UserManager\Entity\User;
use Webware\UserManager\UserInterface;

final readonly class AuthenticationResult
{
    public function __construct(
        public AuthenticationStatus $status,
        public (UserInterface&User)|null $user = null,
    ) {}
}
