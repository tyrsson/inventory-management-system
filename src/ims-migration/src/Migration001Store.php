<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\TinyInteger;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Char;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration001Store implements MigrationInterface
{
    public function getStep(): int
    {
        return 1;
    }

    public function getDescription(): string
    {
        return 'Create store table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('store');
        $create->ifNotExists();

        $create->addColumn(
            (new SmallInteger('store_number', nullable: false))
                ->setOptions([
                    'unsigned' => true,
                    'comment'  => 'Farmers store number (e.g. 207)',
                ])
        );

        $create->addColumn(new Varchar('city', 100));

        $create->addColumn(
            (new Char('state', 2))
                ->setOptions(['comment' => 'Two-letter US state abbreviation'])
        );

        $create->addColumn(
            (new Varchar('pqa_email', 255))
                ->setOptions(['comment' => 'PQA system mailbox for damage images'])
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('store_number'));

        $create->setOptions([
            'engine'         => new Literal('InnoDB'),
            'default charset' => new Literal('utf8mb4'),
            'collate'        => new Literal('utf8mb4_unicode_ci'),
        ]);

        $adapter->query(
            $sql->buildSqlString($create),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }

    public function down(AdapterInterface $adapter): void
    {
        $sql  = new Sql($adapter);
        $drop = (new DropTable('store'))->ifExists();

        $adapter->query(
            $sql->buildSqlString($drop),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
