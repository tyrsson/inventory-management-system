<?php

declare(strict_types=1);

namespace Webware\Acl\Repository;

use Laminas\Permissions\Acl\Role\GenericRole;
use Laminas\Permissions\Acl\Role\Registry;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\TableGateway\TableGateway;

use function array_diff_key;
use function array_fill_keys;
use function count;
use function json_decode;
use function json_encode;

final class RoleRepository
{
    private readonly TableGateway $gateway;

    public function __construct(AdapterInterface $adapter)
    {
        $this->gateway = new TableGateway('acl_role', $adapter);
    }

    /**
     * Returns all roles as an associative array keyed by role_id.
     * Each value is an array of parent role_id strings.
     *
     * @return array<string, string[]>
     */
    public function fetchAll(): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['role_id', 'parent_id']);

        $roles = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $parents          = json_decode($row['parent_id'], true) ?? [];
            $roles[$row['role_id']] = $parents;
        }

        return $roles;
    }

    public function fetchAclRoleRegistry(): Registry
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['role_id', 'parent_id']);

        $roles = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $roles[$row['role_id']] = json_decode($row['parent_id'], true) ?? [];
        }

        $registry  = new Registry();
        $added     = [];
        $pending   = $roles;
        $maxPasses = count($pending) + 1;
        $pass      = 0;

        while ($pending !== [] && $pass++ < $maxPasses) {
            foreach ($pending as $roleId => $parents) {
                if (array_diff_key(array_fill_keys($parents, true), $added) === []) {
                    $parentRoles = $parents ? array_map(static fn($p) => new GenericRole($p), $parents) : null;
                    $registry->add(new GenericRole($roleId), $parentRoles);
                    $added[$roleId] = true;
                    unset($pending[$roleId]);
                }
            }
        }

        return $registry;
    }

    /**
     * Returns all role_ids whose parent_id JSON array contains the given roleId.
     *
     * @return string[]
     */
    public function fetchDirectChildren(string $roleId): array
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['role_id']);
        $select->where->expression('JSON_CONTAINS(parent_id, JSON_QUOTE(?))', [$roleId]);

        $children = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $children[] = $row['role_id'];
        }

        return $children;
    }

    /**
     * Insert or update a role. parent_id is JSON-encoded inside this method.
     *
     * @param string[] $parents
     */
    public function save(string $roleId, array|null $parents): void
    {
        $sql    = $this->gateway->getSql();
        $exists = $sql->select()
            ->columns(['id'])
            ->where(['role_id' => $roleId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($exists)->execute()->current();

        $data = [
            'role_id'   => $roleId,
            'parent_id' => json_encode($parents),
        ];

        if ($row === null) {
            $insert = $sql->insert()->values($data);
            $sql->prepareStatementForSqlObject($insert)->execute();
        } else {
            $update = $sql->update()
                ->set(['parent_id' => $data['parent_id']])
                ->where(['role_id' => $roleId]);
            $sql->prepareStatementForSqlObject($update)->execute();
        }
    }

    public function delete(string $roleId): void
    {
        $sql    = $this->gateway->getSql();
        $delete = $sql->delete()->where(['role_id' => $roleId]);
        $sql->prepareStatementForSqlObject($delete)->execute();
    }
}
