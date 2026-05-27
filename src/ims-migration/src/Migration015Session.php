<?php

declare(strict_types=1);


namespace Ims\Migration;

use Ims\Migration\Column\MediumText;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration015Session implements MigrationInterface
{
    public function getStep(): int
    {
        return 15;
    }

    public function getDescription(): string
    {
        return 'Create session table (PhpDb-backed session storage)';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('session');
        $create->ifNotExists();

        $create->addColumn(
            (new Varchar('id', 64, nullable: false))
                ->setOptions(['comment' => 'PHP session identifier'])
        );

        $create->addColumn(
            (new MediumText('payload', nullable: false))
                ->setOptions(['comment' => 'PHP-serialized session data'])
        );

        $create->addColumn(
            (new Datetime('modified_at', nullable: false))
                ->setOptions([
                    'default'  => new Literal('CURRENT_TIMESTAMP'),
                    'onUpdate' => new Literal('CURRENT_TIMESTAMP'),
                    'comment'  => 'Last write timestamp',
                ])
        );

        $create->addColumn(
            (new Datetime('expires_at', nullable: false))
                ->setOptions(['comment' => 'Absolute expiry (NOW + gc_maxlifetime)'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new Index('expires_at', 'idx_expires_at'));

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
        $sql  = new Sql($adapter);
        $drop = new DropTable('session');
        $drop->ifExists();

        $adapter->query(
            $sql->buildSqlString($drop),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
