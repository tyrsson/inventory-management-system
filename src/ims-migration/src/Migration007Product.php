<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\Enum;
use Ims\Migration\Column\MediumInteger;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
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

final class Migration007Product implements MigrationInterface
{
    public function getStep(): int
    {
        return 7;
    }

    public function getDescription(): string
    {
        return 'Create product table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('product');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('manifest_item_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new SmallInteger('store_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(new Varchar('ao_number', 20));

        $create->addColumn(
            (new MediumInteger('sku', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(new Varchar('vsn', 30, nullable: false, default: ''));
        $create->addColumn(new Varchar('specs', 255, nullable: false, default: ''));

        $create->addColumn(
            (new SmallInteger('case_qty', nullable: false, default: 1))
                ->setOptions([
                    'unsigned' => true,
                    'comment'  => 'Original pieces-per-box from the manifest scan. This row is always one physical piece; case_qty records the box it came from (e.g. 2 for a 2-chair set).',
                ])
        );

        $create->addColumn(
            (new Varchar('serial_number', 100, nullable: true))
                ->setOptions(['comment' => 'Manufacturer serial number; only present on serialised products'])
        );

        $create->addColumn(
            (new Varchar('customer_name', 200, nullable: true))
                ->setOptions(['comment' => 'Set on ticket removal for anti-fraud record'])
        );

        $create->addColumn(
            (new DateTime('removed_at', nullable: true))
                ->setOptions(['comment' => 'NULL = in active inventory'])
        );

        $create->addColumn(
            new Enum('removed_reason', ['Ticket', 'Transfer', 'PQA Resolution', 'Adjustment'], nullable: true)
        );

        $create->addColumn(
            (new Integer('removed_by', nullable: true))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new DateTime('created_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new Index(['store_id', 'removed_at'], 'idx_product_store_active'));
        $create->addConstraint(new Index('ao_number', 'idx_product_ao'));
        $create->addConstraint(new Index('sku', 'idx_product_sku'));
        $create->addConstraint(new Index('serial_number', 'idx_product_serial'));
        $create->addConstraint(new ForeignKey('fk_product_manifest_item', 'manifest_item_id', 'manifest_item', 'id'));
        $create->addConstraint(new ForeignKey('fk_product_store', 'store_id', 'store', 'store_number'));
        $create->addConstraint(new ForeignKey('fk_product_sku', 'sku', 'sku_catalogue', 'sku'));
        $create->addConstraint(new ForeignKey('fk_product_removed_by', 'removed_by', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('product'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
