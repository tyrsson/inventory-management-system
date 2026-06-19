<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Acl\Assertion\AssertionAggregateFactory;
use Webware\Acl\Exception\RuntimeException;
use Webware\Acl\Repository\RoleRepository;
use Webware\Acl\Repository\RuleRepository;
use Webware\Acl\Role\UserRoleIterator;
use Webware\UserManager\UserInterface;

use function is_array;
use function sort;
use function strrpos;
use function substr;

final class Acl extends LaminasAcl implements AclInterface
{
    private bool $loaded = false;

    public function __construct(
        private readonly RoleRepository $roleRepository,
        private readonly RuleRepository $ruleRepository,
        private readonly AssertionAggregateFactory $factory,
        private readonly RouteCollectorInterface $routeCollector,
    ) {}

    public function addRole($role, $parents = null, bool $persist = false)
    {
        parent::addRole($role, $parents);

        if ($persist) {
            $roleId = $role instanceof RoleInterface ? $role->getRoleId() : $role;

            if ($parents === null) {
                $this->roleRepository->save($roleId, null);
            } else {
                $parentIds    = [];
                $parentsArray = is_array($parents) ? $parents : [$parents];
                foreach ($parentsArray as $parent) {
                    $parentIds[] = $parent instanceof RoleInterface ? $parent->getRoleId() : $parent;
                }
                $this->roleRepository->save($roleId, $parentIds);
            }
        }

        return $this;
    }

    public function addResource($resource, $parent = null, bool $persist = false)
    {
        throw RuntimeException::forAclAddResource();
    }

    #[Override]
    public function isAllowed(
        $role = null,
        $resource = null,
        $privilege = null,
    ): bool {
        if (null === $role) {
            return false;
        }

        $this->load();

        // FAIL CLOSED — intentional, do not change to true.
        // Routes must be explicitly registered as ACL resources to be accessible.
        // This is a hard requirement; unregistered routes are always denied.
        if (! $this->hasResource($resource)) {
            return false;
        }

        foreach (new UserRoleIterator($role) as $roleProxy) {
            if (parent::isAllowed($roleProxy, $resource, $privilege)) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    public function isAllowedRoute(
        ?UserInterface $user,
        ResourceInterface $resource,
    ): bool {
        return $this->isAllowed($user, $resource);
    }

    public function getRoles(): array
    {
        $registry = $this->getRoleRegistry();
        $result   = [];
        foreach (array_keys($registry->getRoles()) as $roleId) {
            $parents = [];
            foreach ($registry->getParents($roleId) as $parentId => $parent) {
                $parents[] = $parentId;
            }
            $result[$roleId] = $parents;
        }

        return $result;
    }

    public function getResourceParentId(string $resourceId): ?string
    {
        if (! $this->hasResource($resourceId)) {
            return null;
        }

        return $this->resources[$resourceId]['parent']?->getResourceId();
    }

    protected function getRoleRegistry()
    {
        if (null === $this->roleRegistry) {
            $this->roleRegistry = $this->roleRepository->fetchAclRoleRegistry();
        }

        return $this->roleRegistry;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $allRules = $this->ruleRepository->fetchAll();

        // Build explicit parent map from DB data
        $explicitParents = [];
        foreach ($allRules as $rule) {
            if ($rule['parentResourceId'] !== null) {
                $explicitParents[$rule['resourceId']] = $rule['parentResourceId'];
            }
        }

        $resourceIds = $this->ruleRepository->fetchDistinctResourceIds();
        sort($resourceIds);

        foreach ($resourceIds as $resourceId) {
            if ($this->hasResource($resourceId)) {
                continue;
            }
            // Use explicit parentResourceId from DB — no fallback by design.
            // A missing parent here indicates inconsistent DB state and should surface, not be masked.
            $parent = $explicitParents[$resourceId] ?? null;
            parent::addResource($resourceId, $parent);
        }

        foreach ($allRules as $rule) {
            $type = RuleType::from($rule['type'])->toAclConstant();
            $this->setRule(
                self::OP_ADD,
                $type,
                $rule['roleId'],
                $rule['resourceId'],
                null,
                ($this->factory)($rule['assertions'])
            );
        }

        if ($this->hasRole(self::DEVELOPER_ROLE_ID)) {
            $this->setRule(self::OP_ADD, self::TYPE_ALLOW, self::DEVELOPER_ROLE_ID);
        }

        // Register all known routes as resources so the full hierarchy is available.
        // Routes with no rules are registered here under their nearest ancestor.
        foreach ($this->routeCollector->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '' || $this->hasResource($name)) {
                continue;
            }
            $candidate = $name;
            $parent    = null;
            while (($pos = strrpos($candidate, '.')) !== false) {
                $candidate = substr($candidate, 0, $pos);
                if ($this->hasResource($candidate)) {
                    $parent = $candidate;

                    break;
                }
            }
            parent::addResource($name, $parent);
        }

        $this->loaded = true;
    }
}
