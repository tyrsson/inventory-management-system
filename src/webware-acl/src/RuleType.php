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

namespace Webware\Acl;

use Laminas\Permissions\Acl\Acl as LaminasAcl;

enum RuleType: string
{
    case Allow = 'Allow';
    case Deny  = 'Deny';

    public function toAclConstant(): string
    {
        return match ($this) {
            self::Allow => LaminasAcl::TYPE_ALLOW,
            self::Deny  => LaminasAcl::TYPE_DENY,
        };
    }
}
