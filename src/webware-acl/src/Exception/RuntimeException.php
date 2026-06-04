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

final class RuntimeException extends SplRuntimeException implements ExceptionInterface
{
    public static function forAclAddResource(
        ?ExceptionInterface $previous = null,
    ): self {
        return new self(
            'Direct resource registration is not supported; resources are derived from the acl_rule table.',
            0,
            $previous
        );
    }
}
