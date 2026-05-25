<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration013TransferItem implements MigrationInterface
{
    public function getStep(): int
    {
        return 13;
    }

    public function getDescription(): string
    {
        return 'Create transfer_item table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('transfer_item');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('transfer_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Integer('product_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Integer('confirmed_by', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new DateTime('confirmed_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey(['transfer_id', 'product_id'], 'uq_transfer_item'));
        $create->addConstraint(new ForeignKey('fk_xferi_transfer', 'transfer_id', 'transfer', 'id'));
        $create->addConstraint(new ForeignKey('fk_xferi_product', 'product_id', 'product', 'id'));
        $create->addConstraint(new ForeignKey('fk_xferi_confirmed_by', 'confirmed_by', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('transfer_item'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
