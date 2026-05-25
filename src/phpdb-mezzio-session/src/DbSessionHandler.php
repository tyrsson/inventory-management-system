<?php

declare(strict_types=1);


namespace PhpDb\Session;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Session\Sql\Insert;
use PhpDb\Sql\Delete;
use PhpDb\Sql\Sql;
use SessionHandlerInterface;

use function date;
use function ini_get;
use function time;

final class DbSessionHandler implements SessionHandlerInterface
{
    private readonly Sql $sql;

    public function __construct(AdapterInterface $adapter)
    {
        $this->sql = new Sql($adapter, 'session');
    }

    #[\Override]
    public function open(string $savePath, string $name): bool
    {
        return true;
    }

    #[\Override]
    public function close(): bool
    {
        return true;
    }

    #[\Override]
    public function read(string $id): string|false
    {
        $select = $this->sql->select()
            ->columns(['payload'])
            ->where(['id' => $id]);
        $select->where->greaterThan('expires_at', date('Y-m-d H:i:s'));

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();
        $row    = $result->current();

        return is_array($row) ? $row['payload'] : false;
    }

    #[\Override]
    public function write(string $id, string $data): bool
    {
        $maxLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt   = date('Y-m-d H:i:s', time() + $maxLifetime);
        $now         = date('Y-m-d H:i:s');

        $this->sql->prepareStatementForSqlObject(
            (new Insert('session'))
                ->values([
                    'id'          => $id,
                    'payload'     => $data,
                    'expires_at'  => $expiresAt,
                    'modified_at' => $now,
                ])
        )->execute();

        return true;
    }

    #[\Override]
    public function destroy(string $id): bool
    {
        /** @var Delete $delete */
        $delete = $this->sql->delete()->where(['id' => $id]);

        $this->sql->prepareStatementForSqlObject($delete)->execute();

        return true;
    }

    #[\Override]
    public function gc(int $maxLifetime): int|false
    {
        $cutoff = date('Y-m-d H:i:s');

        /** @var Delete $delete */
        $delete = $this->sql->delete();
        $delete->where->lessThan('expires_at', $cutoff);

        $result = $this->sql->prepareStatementForSqlObject($delete)->execute();

        return $result->getAffectedRows();
    }
}
