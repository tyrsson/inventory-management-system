<?php

declare(strict_types=1);

namespace Ims\Migration\Column;

use PhpDb\Sql\Ddl\Column\Integer;

/**
 * MEDIUMINT column — not provided by phpdb core.
 */
final class MediumInteger extends Integer
{
    /** @var string */
    protected string $type = 'MEDIUMINT';
}
