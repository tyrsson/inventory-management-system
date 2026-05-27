<?php

declare(strict_types=1);

namespace Webware\Acl;

use Fig\Http\Message\RequestMethodInterface as HttpMethod;
use Mezzio\Session\Persistence\Http;

interface PrivilegeInterface
{
    public final const string READ   = 'read';
    public final const string CREATE = 'create';
    public final const string UPDATE = 'update';
    public final const string DELETE = 'delete';
    public final const array METHOD_PRIVILEGE_MAP = [
        HttpMethod::METHOD_GET    => self::READ,
        HttpMethod::METHOD_HEAD   => self::READ,
        HttpMethod::METHOD_POST   => self::CREATE,
        HttpMethod::METHOD_PUT    => self::UPDATE,
        HttpMethod::METHOD_PATCH  => self::UPDATE,
        HttpMethod::METHOD_DELETE => self::DELETE,
    ];

    public function getPrivilegeId(): string;
}
