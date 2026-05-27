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

use RuntimeException as SplRuntimeException;


use function sprintf;

final class RuntimeException extends SplRuntimeException implements ExceptionInterface
{
    public static function forAclBuildEvent(
        string $message,
        string $eventClass,
        ?ExceptionInterface $previous = null
    ): self {
        return new self(
            sprintf(
                'An error occurred while dispatching event %s: %s',
                $eventClass,
                $message
            ),
            0,
            $previous
        );
    }
}
