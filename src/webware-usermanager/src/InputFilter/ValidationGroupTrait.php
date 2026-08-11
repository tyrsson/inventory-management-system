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

namespace Webware\UserManager\InputFilter;

trait ValidationGroupTrait
{
    public const array REGISTRATION_VALIDATION_GROUP = [
        'firstName',
        'lastName',
        'email',
        'passwordHash',
        'confirmPasswordHash',
        'verificationToken',
        'roleId',
        'active',
    ];

    public const array UPDATE_VALIDATION_GROUP = [
        'id',
        'firstName',
        'lastName',
        'email',
        'roleId',
        'active',
    ];
}
