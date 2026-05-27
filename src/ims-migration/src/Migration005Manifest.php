<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Date;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration005Manifest implements MigrationInterface
{
    public function getStep(): int
    {
        return 5;
    }

    public function getDescription(): string
    {
        return 'Create manifest table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('manifest');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new SmallInteger('store_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Varchar('reference', 100, nullable: true))
                ->setOptions(['comment' => 'DC manifest / bill-of-lading reference'])
        );

        $create->addColumn(new Date('received_date'));

        $create->addColumn(
            (new Integer('created_by', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new DateTime('created_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addColumn(
            (new Varchar('csv_path', 255, nullable: true))
                ->setOptions(['comment' => 'Relative path to the uploaded CSV file; null once processing is complete'])
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new Index('store_id', 'idx_manifest_store'));
        $create->addConstraint(new ForeignKey('fk_manifest_store', 'store_id', 'store', 'store_number'));
        $create->addConstraint(new ForeignKey('fk_manifest_created_by', 'created_by', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('manifest'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
