<?php

declare(strict_types=1);

namespace PhpDb\Session\Sql;

use PhpDb\Sql\Insert as BaseInsert;

/**
 * Session-specific INSERT with upsert clause.
 *
 * Overrides the INSERT specification to append ON DUPLICATE KEY UPDATE for the
 * three mutable session columns. The parent processInsert() substitutes only
 * %1$s (table), %2$s (columns), %3$s (values); everything after %3$s is emitted
 * verbatim, so no additional process* method is required.
 *
 * @todo When PhpDb\Adapter\Platform\PlatformInterface gains buildUpsertClause()
 *       support, delegate to that instead of hardcoding MySQL syntax here so
 *       this class becomes portable across all PhpDb driver packages.
 */
final class Insert extends BaseInsert
{
    /** @var array[]|string[] */
    protected array $specifications = [
        self::SPECIFICATION_INSERT
                                   => 'INSERT INTO %1$s (%2$s) VALUES (%3$s)'
            . ' ON DUPLICATE KEY UPDATE'
            . ' payload = VALUES(payload)'
            . ', expires_at = VALUES(expires_at)'
            . ', modified_at = VALUES(modified_at)',
        self::SPECIFICATION_SELECT => 'INSERT INTO %1$s %2$s %3$s',
    ];
}
