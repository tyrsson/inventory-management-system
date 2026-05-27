<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration003MajorCode implements MigrationInterface
{
    public function getStep(): int
    {
        return 3;
    }

    public function getDescription(): string
    {
        return 'Create major_code table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('major_code');
        $create->ifNotExists();

        $create->addColumn(
            (new SmallInteger('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(new Varchar('code', 20));
        $create->addColumn(new Varchar('description', 100));

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey('code', 'uq_major_code'));

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
            $sql->buildSqlString((new DropTable('major_code'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
