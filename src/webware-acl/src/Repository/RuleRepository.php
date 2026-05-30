<?php

declare(strict_types=1);

namespace Webware\Acl\Repository;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\TableGateway\TableGateway;

use function json_decode;
use function json_encode;

final class RuleRepository
{
    private readonly TableGateway $gateway;

    public function __construct(AdapterInterface $adapter)
    {
        $this->gateway = new TableGateway('acl_rule', $adapter);
    }

    /**
     * Returns all rules as plain arrays with decoded assertions.
     *
     * Each row: ['type' => 'allow'|'deny', 'role_id' => string,
     *             'resource_id' => string, 'assertions' => string[]]
     *
     * @return array<int, array{type: string, role_id: string, resource_id: string, assertions: string[]}>
     */
    public function fetchAll(): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['type', 'role_id', 'resource_id', 'assertions']);

        $rules = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $rules[] = [
                'type'        => $row['type'],
                'role_id'     => $row['role_id'],
                'resource_id' => $row['resource_id'],
                'assertions'  => json_decode($row['assertions'], true) ?? [],
            ];
        }

        return $rules;
    }

    /**
     * Returns the distinct set of resource_id values across all rules.
     *
     * @return string[]
     */
    public function fetchDistinctResourceIds(): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->columns(['resource_id'])
            ->quantifier('DISTINCT');

        $ids = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $ids[] = $row['resource_id'];
        }

        return $ids;
    }

    /**
     * Returns a single rule row for the given (role_id, resource_id) pair, or null.
     *
     * @return array{type: string, role_id: string, resource_id: string, assertions: string[]}|null
     */
    public function findByRoleAndResource(string $roleId, string $resourceId): ?array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()
            ->columns(['type', 'role_id', 'resource_id', 'assertions'])
            ->where(['role_id' => $roleId, 'resource_id' => $resourceId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if ($row === false || $row === null) {
            return null;
        }

        return [
            'type'        => $row['type'],
            'role_id'     => $row['role_id'],
            'resource_id' => $row['resource_id'],
            'assertions'  => json_decode($row['assertions'], true) ?? [],
        ];
    }

    /**
     * Insert or update a rule (upsert on the unique key role_id + resource_id).
     *
     * @param string[] $assertions
     */
    public function save(string $type, string $roleId, string $resourceId, array|null $assertions): bool
    {
        $sql    = $this->gateway->getSql();
        $exists = $sql->select()
            ->columns(['id'])
            ->where(['role_id' => $roleId, 'resource_id' => $resourceId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($exists)->execute()->current();

        $data = [
            'type'        => $type,
            'role_id'     => $roleId,
            'resource_id' => $resourceId,
        ];

        if ($assertions !== null) {
            $data['assertions'] = json_encode($assertions);
        }

        if ($row === false || $row === null) {
            $insert = $sql->insert()->values($data);
            $result = $sql->prepareStatementForSqlObject($insert)->execute();
        } else {
            $set = ['type' => $type];
            if ($assertions !== null) {
                $set['assertions'] = json_encode($assertions);
            }
            $update = $sql->update()
                ->set($set)
                ->where(['role_id' => $roleId, 'resource_id' => $resourceId]);
            $result = $sql->prepareStatementForSqlObject($update)->execute();
        }

        return $result->getAffectedRows() > 0;
    }

    /**
     * Update only the type column for a specific (role_id, resource_id) pair.
     */
    public function updateType(string $roleId, string $resourceId, string $newType): bool
    {
        $sql    = $this->gateway->getSql();
        $update = $sql->update()
            ->set(['type' => $newType])
            ->where(['role_id' => $roleId, 'resource_id' => $resourceId]);
        $result = $sql->prepareStatementForSqlObject($update)->execute();
        return $result->getAffectedRows() > 0;
    }

    /**
     * Delete the rule for the given (role_id, resource_id) pair.
     */
    public function delete(string $roleId, string $resourceId): bool
    {
        $sql    = $this->gateway->getSql();
        $delete = $sql->delete()->where(['role_id' => $roleId, 'resource_id' => $resourceId]);
        $result = $sql->prepareStatementForSqlObject($delete)->execute();
        return $result->getAffectedRows() > 0;
    }
}
