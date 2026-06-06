<?php

declare(strict_types=1);

namespace Webware\Acl;

enum GrantMode: string
{
    case None      = 'none';
    case Explicit  = 'explicit';
    case Inherited = 'inherited';
}
