<?php

declare(strict_types=1);

namespace Ims\Migration\Column;

use PhpDb\Sql\Ddl\Column\Text;

/**
 * MEDIUMTEXT column — not provided by phpdb core.
 */
final class MediumText extends Text
{
    protected string $type = 'MEDIUMTEXT';
}
