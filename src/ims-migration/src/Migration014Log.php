<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\BigInteger;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\Text;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration014Log implements MigrationInterface
{
    public function getStep(): int
    {
        return 14;
    }

    public function getDescription(): string
    {
        return 'Create log table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('log');
        $create->ifNotExists();

        $create->addColumn(
            (new BigInteger('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(new Varchar('channel', 64, nullable: false, default: ''));
        $create->addColumn(new Varchar('level', 20, nullable: false, default: ''));
        $create->addColumn(new Varchar('uuid', 36, nullable: true));
        $create->addColumn(new Text('message'));

        $create->addColumn(
            (new Integer('time', nullable: false))
                ->setOptions(['unsigned' => true, 'comment' => 'Unix timestamp'])
        );

        $create->addColumn(
            (new Varchar('user_identifier', 255, nullable: true))
                ->setOptions(['comment' => 'Email or other identity at log time'])
        );

        $create->addColumn(
            (new Json('context', nullable: true))
                ->setOptions(['comment' => 'Monolog context and extra data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new Index('level', 'idx_log_level'));
        $create->addConstraint(new Index('channel', 'idx_log_channel'));
        $create->addConstraint(new Index('time', 'idx_log_time'));

        $create->setOptions([
            'engine'          => new Literal('InnoDB'),
            'default charset' => new Literal('utf8mb4'),
            'collate'         => new Literal('utf8mb4_unicode_ci'),
        ]);

        $adapter->query(
            $sql->buildSqlString($create),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }

    public function down(AdapterInterface $adapter): void
    {
        $sql = new Sql($adapter);

        $adapter->query(
            $sql->buildSqlString((new DropTable('log'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
