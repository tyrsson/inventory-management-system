<?php

declare(strict_types=1);

namespace Webware\Acl;

enum Schema: string
{
    case Roles = 'acl_role';
    case Rules = 'acl_rule';
}
