<?php

declare(strict_types=1);

namespace Webware\Acl;

use Fig\Http\Message\RequestMethodInterface as HttpMethod;

interface PrivilegeInterface
{
    final public const string READ = 'read';

    final public const string CREATE = 'create';

    final public const string UPDATE = 'update';

    final public const string DELETE = 'delete';

    final public const array METHOD_PRIVILEGE_MAP = [
        HttpMethod::METHOD_GET    => self::READ,
        HttpMethod::METHOD_HEAD   => self::READ,
        HttpMethod::METHOD_POST   => self::CREATE,
        HttpMethod::METHOD_PUT    => self::UPDATE,
        HttpMethod::METHOD_PATCH  => self::UPDATE,
        HttpMethod::METHOD_DELETE => self::DELETE,
    ];

    public function getPrivilegeId(): string;
}
