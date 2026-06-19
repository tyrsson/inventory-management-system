<?php

declare(strict_types=1);

namespace Webware\ResultSet;

use PhpDb\ResultSet\RowPrototypeInterface;

interface WithRowDataPrototypeInterface extends RowPrototypeInterface
{
    public function withRowData(array $withRowData): static;
}
