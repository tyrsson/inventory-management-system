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

namespace Webware\Acl\Exception;

/**
 * Thrown by CommandHandlerMiddleware when an ownership assertion fails.
 *
 * Indicates a command was submitted for a resource the user does not own.
 * Because the application UI never surfaces controls that would allow an
 * unauthorised user to initiate the command, this exception indicates
 * intentional probing and should be logged at critical level.
 */
final class AccessDeniedException extends RuntimeException
{
}
