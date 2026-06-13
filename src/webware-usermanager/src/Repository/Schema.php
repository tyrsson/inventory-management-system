<?php

declare(strict_types=1);

namespace Webware\UserManager\Repository;

use PhpDb\Sql\TableIdentifier;
use Webware\Core\SchemaInterface;

enum Schema: string implements SchemaInterface
{
    case User = 'user';

    public function table(): TableIdentifier
    {
        return new TableIdentifier($this->value);
    }
}
