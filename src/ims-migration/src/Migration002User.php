<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\TinyInteger;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

// NOTE: role_id is a plain VARCHAR — not a FK. Roles are managed in config.

final class Migration002User implements MigrationInterface
{
    public function getStep(): int
    {
        return 2;
    }

    public function getDescription(): string
    {
        return 'Create user table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('user');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new SmallInteger('storeId', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(new Json('roleId', nullable: false));

        $create->addColumn(new Varchar('firstName', 75));
        $create->addColumn(new Varchar('lastName', 75));
        $create->addColumn(new Varchar('email', 255));

        $create->addColumn(
            (new Varchar('passwordHash', 255))
                ->setOptions(['comment' => 'bcrypt hash; never store plain text'])
        );

        $create->addColumn(new TinyInteger('active', nullable: false, default: 0));

        $create->addColumn(new Varchar('verificationToken', 36, nullable: true));
        $create->addColumn(new Datetime('tokenCreatedAt', nullable: true));

        $create->addColumn(
            new Datetime('created_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey('email', 'uq_user_email'));
        $create->addConstraint(new ForeignKey('fk_user_store', 'storeId', 'store', 'store_number'));

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
            $sql->buildSqlString((new DropTable('user'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
