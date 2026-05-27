<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;

interface MigrationInterface
{
    /** Integer version — matches the numeric prefix of the corresponding schema file (e.g. 23). */
    public function getStep(): int;

    /** Human-readable description shown by the runner. */
    public function getDescription(): string;

    /** Apply the migration. Must be idempotent. */
    public function up(AdapterInterface $adapter): void;

    /** Reverse the migration. */
    public function down(AdapterInterface $adapter): void;
}
