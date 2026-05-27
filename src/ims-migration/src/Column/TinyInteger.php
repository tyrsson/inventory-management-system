<?php

declare(strict_types=1);

namespace Ims\Migration\Column;

use PhpDb\Sql\Ddl\Column\Integer;

/**
 * TINYINT column — not provided by phpdb core.
 */
final class TinyInteger extends Integer
{
    /** @var string */
    protected string $type = 'TINYINT';
}
