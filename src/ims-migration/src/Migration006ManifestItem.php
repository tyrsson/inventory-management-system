<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\MediumInteger;
use Ims\Migration\Column\TinyInteger;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Text;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration006ManifestItem implements MigrationInterface
{
    public function getStep(): int
    {
        return 6;
    }

    public function getDescription(): string
    {
        return 'Create manifest_item table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('manifest_item');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('manifest_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Varchar('ao_number', 20))
                ->setOptions(['comment' => 'AO# / Tag ID from SKU card (e.g. A006523361)'])
        );

        $create->addColumn(
            (new MediumInteger('sku', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Varchar('vsn', 30, nullable: false, default: ''))
                ->setOptions(['comment' => 'Vendor Stock Number from SKU card'])
        );

        $create->addColumn(
            (new Varchar('specs', 255, nullable: false, default: ''))
                ->setOptions(['comment' => 'Finish / Cover / Size / ST from SKU card'])
        );

        $create->addColumn(
            (new SmallInteger('case_qty', nullable: false, default: 1))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new TinyInteger('is_damaged', nullable: false, default: 0))
                ->setOptions(['comment' => 'Flagged damaged at time of scan'])
        );

        $create->addColumn(
            (new Text('notes', nullable: true))
                ->setOptions(['comment' => 'Scan-time damage notes'])
        );

        $create->addColumn(
            (new Integer('scanned_by', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new Datetime('scanned_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey(['manifest_id', 'ao_number'], 'uq_manifest_item_ao'));
        $create->addConstraint(new Index('ao_number', 'idx_mi_ao'));
        $create->addConstraint(new ForeignKey('fk_mi_manifest', 'manifest_id', 'manifest', 'id'));
        $create->addConstraint(new ForeignKey('fk_mi_sku', 'sku', 'sku_catalogue', 'sku'));
        $create->addConstraint(new ForeignKey('fk_mi_scanned_by', 'scanned_by', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('manifest_item'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
