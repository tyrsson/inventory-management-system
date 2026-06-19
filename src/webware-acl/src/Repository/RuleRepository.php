<?php

declare(strict_types=1);

namespace Webware\Acl\Repository;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Select;
use PhpDb\TableGateway\TableGateway;
use Webware\Acl\Entity\Rule;
use Webware\Acl\RuleType;
use Webware\ResultSet\WithRowDataResultSet;

use function json_decode;
use function json_encode;

final class RuleRepository
{
    private readonly TableGateway $gateway;

    public function __construct(AdapterInterface $adapter)
    {
        $this->gateway = new TableGateway(
            Schema::Rules->table(),
            $adapter,
            null,
            new WithRowDataResultSet(
                rowPrototype: new Rule(),
            )
        );
    }

    /**
     * Returns all rules as plain arrays with decoded assertions.
     *
     * Each row: ['type' => 'Allow'|'Deny', 'roleId' => string,
     *             'resourceId' => string, 'assertions' => string[]]
     *
     * @return array<int, array{type: string, roleId: string, resourceId: string, assertions: string[]}>
     */
    public function fetchAll(): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['type', 'roleId', 'resourceId', 'assertions', 'parentResourceId']);

        $rules = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $rules[] = [
                'type'             => $row['type'],
                'roleId'           => $row['roleId'],
                'resourceId'       => $row['resourceId'],
                'assertions'       => $row['assertions'] === null ? null : json_decode($row['assertions'], true),
                'parentResourceId' => $row['parentResourceId'] ?? null,
            ];
        }

        return $rules;
    }

    /**
     * Returns the distinct set of resourceId values across all rules.
     *
     * @return string[]
     */
    public function fetchDistinctResourceIds(): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->columns(['resourceId'])
            ->quantifier(Select::QUANTIFIER_DISTINCT);

        $ids = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $ids[] = $row['resourceId'];
        }

        return $ids;
    }

    /**
     * Returns a single rule row for the given (roleId, resourceId) pair, or null.
     *
     * @return array{type: string, roleId: string, resourceId: string, assertions: string[]}|null
     */
    public function findByRoleAndResource(string $roleId, string $resourceId): ?array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->columns(['type', 'roleId', 'resourceId', 'assertions'])
            ->where(['roleId' => $roleId, 'resourceId' => $resourceId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if ($row === false || $row === null) {
            return null;
        }

        return [
            'type'        => $row['type'],
            'roleId'      => $row['roleId'],
            'resourceId'  => $row['resourceId'],
            'assertions'  => json_decode($row['assertions'], true) ?? [],
        ];
    }

    /**
     * Insert or update a rule (upsert on the unique key roleId + resourceId).
     * Returns the rule ID on success, false on failure.
     * 
     * $this->allow(Role, Resource, Privilege, Assertions) in the ACL corresponds to save(RuleType::Allow, Role, Resource, Assertions) here.;
     *
     * @param string[] $assertions
     */
    public function save(RuleType $type, string $roleId, string $resourceId, ?array $assertions, ?string $parentResourceId = null): int|false
    {
        if ($assertions === [] || $assertions === [0 => '']) {
            $assertions = null;
        }

        $sql    = $this->gateway->getSql();
        $exists = $sql->select()
            ->columns(['id'])
            ->where(['roleId' => $roleId, 'resourceId' => $resourceId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($exists)->execute()->current();

        $data = [
            'type'       => $type->value,
            'roleId'     => $roleId,
            'resourceId' => $resourceId,
        ];

        if ($parentResourceId !== null) {
            $data['parentResourceId'] = $parentResourceId;
        }

        if ($assertions !== null) {
            $data['assertions'] = json_encode($assertions);
        }

        if ($row === false || $row === null) {
            $insert = $sql->insert()->values($data);
            $sql->prepareStatementForSqlObject($insert)->execute();

            $id = $this->gateway->getAdapter()->getDriver()->getConnection()->getLastGeneratedValue();

            return $id !== null ? (int) $id : false;
        } else {
            $set = ['type' => $type->value];
            if ($parentResourceId !== null) {
                $set['parentResourceId'] = $parentResourceId;
            }
            if ($assertions !== null) {
                $set['assertions'] = json_encode($assertions);
            }
            $update = $sql->update()
                ->set($set)
                ->where(['roleId' => $roleId, 'resourceId' => $resourceId]);
            $result = $sql->prepareStatementForSqlObject($update)->execute();

            return $result->getAffectedRows() >= 0 ? (int) $row['id'] : false;
        }
    }

    /**
     * Update only the type column for a specific (roleId, resourceId) pair.
     */
    public function updateType(string $roleId, string $resourceId, RuleType $newType): bool
    {
        $sql    = $this->gateway->getSql();
        $update = $sql->update()
            ->set(['type' => $newType->value])
            ->where(['roleId' => $roleId, 'resourceId' => $resourceId]);
        $result = $sql->prepareStatementForSqlObject($update)->execute();

        return $result->getAffectedRows() > 0;
    }

    /**
     * Delete the rule for the given (roleId, resourceId) pair.
     */
    public function delete(string $roleId, string $resourceId): bool
    {
        $sql    = $this->gateway->getSql();
        $delete = $sql->delete()->where(['roleId' => $roleId, 'resourceId' => $resourceId]);
        $result = $sql->prepareStatementForSqlObject($delete)->execute();

        return $result->getAffectedRows() > 0;
    }
}
