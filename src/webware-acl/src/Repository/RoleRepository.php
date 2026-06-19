<?php

declare(strict_types=1);

namespace Webware\Acl\Repository;

use Laminas\Permissions\Acl\Role\Registry;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\TableGateway\TableGateway;
use Webware\Acl\Entity\Role;
use Webware\ResultSet\WithRowDataResultSet;

use function array_shift;
use function json_encode;

final class RoleRepository
{
    private readonly TableGateway $gateway;

    public function __construct(AdapterInterface $adapter)
    {
        $this->gateway = new TableGateway(
            Schema::Roles->table(),
            $adapter,
            null,
            new WithRowDataResultSet(
                rowPrototype: new Role(),
            )
        );
    }

    /**
     * @return Role[]
     */
    public function fetchAll(): array
    {
        $roles = [];
        foreach ($this->gateway->select() as $role) {
            $roles[] = $role;
        }

        return $roles;
    }

    public function fetchAclRoleRegistry(): Registry
    {
        $roles = $this->fetchAll();

        // Index by roleId for O(1) lookup
        $map = [];
        foreach ($roles as $role) {
            $map[$role->getRoleId()] = $role;
        }

        // Kahn's topological sort — build in-degree and adjacency list
        $inDegree = [];
        $children = [];
        foreach ($map as $roleId => $role) {
            $inDegree[$roleId] ??= 0;
            foreach ($role->parentId ?? [] as $parent) {
                $parentId = $parent->getRoleId();
                if (isset($map[$parentId])) {
                    $inDegree[$roleId]++;
                    $children[$parentId][] = $roleId;
                }
            }
        }

        // Seed the queue with roots (roles that have no known parents)
        $queue = [];
        foreach ($inDegree as $roleId => $degree) {
            if ($degree === 0) {
                $queue[] = $roleId;
            }
        }

        $registry = new Registry();
        while ($queue !== []) {
            $roleId = array_shift($queue);
            $role   = $map[$roleId];
            $registry->add($role, $role->parentId ?: null);
            foreach ($children[$roleId] ?? [] as $childId) {
                if (--$inDegree[$childId] === 0) {
                    $queue[] = $childId;
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
        $select = $sql->select()->columns(['roleId']);
        $select->where->expression('JSON_CONTAINS(parentId, JSON_QUOTE(?))', [$roleId]);

        $children = [];
        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $children[] = $row['roleId'];
        }

        return $children;
    }

    /**
     * Insert or update a role. parentId is JSON-encoded inside this method.
     *
     * @param string[]|null $parents
     */
    public function save(string $roleId, ?array $parents): int|string|false
    {
        $sql    = $this->gateway->getSql();
        $exists = $sql->select()
            ->columns(['id'])
            ->where(['roleId' => $roleId])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($exists)->execute()->current();

        $data = [
            'roleId'   => $roleId,
            'parentId' => json_encode($parents),
        ];

        if (! $row) {
            $insert = $sql->insert()->values($data);
            $sql->prepareStatementForSqlObject($insert)->execute();

            return $this->gateway->getAdapter()->getDriver()->getConnection()->getLastGeneratedValue();
        }

        $update = $sql->update()
            ->set(['parentId' => $data['parentId']])
            ->where(['roleId' => $roleId]);
        $result = $sql->prepareStatementForSqlObject($update)->execute();

        return $result->getAffectedRows() > 0 ? $row['id'] : false;
    }

    public function delete(string $roleId): void
    {
        $sql    = $this->gateway->getSql();
        $delete = $sql->delete()->where(['roleId' => $roleId]);
        $sql->prepareStatementForSqlObject($delete)->execute();
    }

    /**
     * Removes the given roleId from the parentId JSON array of any role that lists it as a parent.
     */
    public function removeFromParents(string $roleId): void
    {
        $sql    = $this->gateway->getSql();
        $select = $sql->select()->columns(['id', 'parentId']);
        $select->where->expression('JSON_CONTAINS(parentId, JSON_QUOTE(?))', [$roleId]);

        foreach ($sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $parents = json_decode($row['parentId'], true) ?? [];
            $parents = array_values(array_filter($parents, static fn ($p) => $p !== $roleId));
            $update  = $sql->update()
                ->set(['parentId' => json_encode($parents)])
                ->where(['id' => $row['id']]);
            $sql->prepareStatementForSqlObject($update)->execute();
        }
    }
}
