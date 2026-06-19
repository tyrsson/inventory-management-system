<?php

declare(strict_types=1);

namespace Webware\Core;

use PhpDb\Sql\TableIdentifier;

interface SchemaInterface
{
    public function table(): TableIdentifier;
}
