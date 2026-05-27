<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\Middleware;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;
use Webware\Acl\Entity\Role;
use Webware\Acl\PrivilegeInterface;

use function array_flip;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function is_int;

/**
 * Assembles the Access Control page view model and attaches it to the request
 * as an attribute before passing control to AclOverviewHandler.
 *
 * Follows the same pattern as Mezzio\Router\Middleware\RouteMiddleware:
 * data assembly is separated from rendering. The handler reads the attribute
 * and calls $this->template->render() with it.
 *
 * Attribute key: BuildAccessControlMiddleware::class
 *
 * View model shape:
 *   unprotectedRoutes  array<string, string[]>                                   routeName → allowedMethods
 *   protectedRoutes    array<string, array{methods:string[], derivedPrivileges:string[], ruleCount:int, roles:string[], hasAssertions:bool, rules:array<int,array<string,mixed>>, resourcePk:int}>
 *   roleTree           array<int, array{id:int, roleId:string, parents:int[]}>   rolePk → node
 *   roleChildren       array<int, int[]>                                         parentPk → childPks
 *   routeFilters       array{all:int, unprotected:int, protected:int}
 *   roles              array<int, \Webware\Acl\Entity\Role>
 *   roleParents        array<int, int[]>                                         childPk → parentPks
 */
final readonly class BuildAccessControlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $config,
        private RouteCollectorInterface $routeCollector,
        private AssertionManager $assertionManager
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config          = $request->getAttribute(AclInterface::class) ?? $this->config;
        $configRoles     = $config['roles'] ?? [];     // [roleName => parentsArray[]]
        $configResources = $config['resources'] ?? []; // ['routeName' => true, ...]
        $configAllow     = $config['allow'] ?? [];     // [roleName => [routeName, ...]]
        $configDeny      = $config['deny'] ?? [];      // [roleName => [routeName, ...]]
        $assertionOptions = $this->assertionManager->getAssertionOptions(); // [['label' => string, 'value' => alias], ...]

        // Stable synthetic PK assignment: array index → roleName
        $roleNames = array_keys($configRoles);
        $rolePkMap = array_flip($roleNames); // roleName → pk

        // Build the protected set: union of all three sources
        $protectedSet = $configResources;
        foreach ($configAllow as $routeList) {
            foreach (array_keys($this->normalizeRouteList($routeList)) as $routeName) {
                $protectedSet[$routeName] = true;
            }
        }
        foreach ($configDeny as $routeList) {
            foreach (array_keys($this->normalizeRouteList($routeList)) as $routeName) {
                $protectedSet[$routeName] = true;
            }
        }

        $unprotectedRoutes = [];
        $protectedRoutes   = [];

        foreach ($this->routeCollector->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }

            $methods = $route->getAllowedMethods() ?? ['GET'];

            if (! isset($protectedSet[$name])) {
                $unprotectedRoutes[$name] = $methods;
                continue;
            }

            // Build rule rows from config allow/deny for this resource
            $rules           = [];
            $rolesOnResource = [];
            $syntheticId     = 0;
            $hasAssertions   = false;

            // Detect Laminas ACL resource parent (string value = parent resource ID)
            $parentResource = isset($configResources[$name]) && is_string($configResources[$name])
                ? $configResources[$name]
                : null;

            foreach ($configAllow as $roleId => $allowedRoutes) {
                $normalized = $this->normalizeRouteList($allowedRoutes);
                if (isset($normalized[$name])) {
                    $assertions               = array_values(array_unique($normalized[$name]));
                    $rules[]                  = [
                        'id'            => ++$syntheticId,
                        'role_id'       => $roleId,
                        'resource_id'   => $name,
                        'privilege_id'  => '',
                        'type'          => 'allow',
                        'assertions'    => $assertions,
                        'inherited'     => false,
                        'inherited_from' => null,
                    ];
                    $rolesOnResource[$roleId] = true;
                    if ($assertions !== []) {
                        $hasAssertions = true;
                    }
                }
            }

            foreach ($configDeny as $roleId => $deniedRoutes) {
                $normalized = $this->normalizeRouteList($deniedRoutes);
                if (isset($normalized[$name])) {
                    $rules[]                  = [
                        'id'            => ++$syntheticId,
                        'role_id'       => $roleId,
                        'resource_id'   => $name,
                        'privilege_id'  => '',
                        'type'          => 'deny',
                        'assertions'    => [],
                        'inherited'     => false,
                        'inherited_from' => null,
                    ];
                    $rolesOnResource[$roleId] = true;
                }
            }

            // Pull in rules inherited from the parent resource (read-only in the UI)
            if ($parentResource !== null) {
                foreach ($configAllow as $roleId => $allowedRoutes) {
                    $normalized = $this->normalizeRouteList($allowedRoutes);
                    if (isset($normalized[$parentResource])) {
                        $assertions               = $normalized[$parentResource];
                        $rules[]                  = [
                            'id'             => ++$syntheticId,
                            'role_id'        => $roleId,
                            'resource_id'    => $name,
                            'privilege_id'   => '',
                            'type'           => 'allow',
                            'assertions'     => $assertions,
                            'inherited'      => true,
                            'inherited_from' => $parentResource,
                        ];
                        $rolesOnResource[$roleId] = true;
                        if ($assertions !== []) {
                            $hasAssertions = true;
                        }
                    }
                }
                foreach ($configDeny as $roleId => $deniedRoutes) {
                    $normalized = $this->normalizeRouteList($deniedRoutes);
                    if (isset($normalized[$parentResource])) {
                        $rules[]                  = [
                            'id'             => ++$syntheticId,
                            'role_id'        => $roleId,
                            'resource_id'    => $name,
                            'privilege_id'   => '',
                            'type'           => 'deny',
                            'assertions'     => [],
                            'inherited'      => true,
                            'inherited_from' => $parentResource,
                        ];
                        $rolesOnResource[$roleId] = true;
                    }
                }
            }

            $derivedPrivileges = array_values(array_unique(array_map(
                static fn(string $m): string => PrivilegeInterface::METHOD_PRIVILEGE_MAP[$m] ?? PrivilegeInterface::READ,
                $methods,
            )));

            $protectedRoutes[$name] = [
                'methods'           => $methods,
                'derivedPrivileges' => $derivedPrivileges,
                'ruleCount'         => count($rules),
                'roles'             => array_keys($rolesOnResource),
                'hasAssertions'     => $hasAssertions,
                'rules'             => $rules,
                'parentResource'    => $parentResource,
                'resourcePk'        => 0,
            ];
        }

        // Build roles, roleParents, roleTree, and roleChildren from config
        $roles       = [];
        $roleParents = [];

        foreach ($roleNames as $pk => $roleId) {
            $roles[$pk]  = new Role($roleId);
            $parentPks   = [];
            foreach ($configRoles[$roleId] as $parentName) {
                if (isset($rolePkMap[$parentName])) {
                    $parentPks[] = $rolePkMap[$parentName];
                }
            }
            $roleParents[$pk] = $parentPks;
        }

        $roleTree = [];
        foreach ($roles as $pk => $role) {
            $roleTree[$pk] = [
                'id'      => $pk,
                'roleId'  => $role->roleId,
                'parents' => $roleParents[$pk],
            ];
        }

        $roleChildren = [];
        foreach ($roleParents as $childPk => $parentPks) {
            foreach ($parentPks as $parentPk) {
                $roleChildren[$parentPk][] = $childPk;
            }
        }

        $unprotectedCount = count($unprotectedRoutes);
        $protectedCount   = count($protectedRoutes);

        $viewModel = [
            'unprotectedRoutes' => $unprotectedRoutes,
            'protectedRoutes'   => $protectedRoutes,
            'roleTree'          => $roleTree,
            'roleChildren'      => $roleChildren,
            'routeFilters'      => [
                'all'         => $unprotectedCount + $protectedCount,
                'unprotected' => $unprotectedCount,
                'protected'   => $protectedCount,
            ],
            'roles'       => $roles,
            'roleParents' => $roleParents,
            'assertions'  => $assertionOptions,
        ];

        return $handler->handle($request->withAttribute(self::class, $viewModel));
    }

    /**
     * Normalises an allow/deny route list to routeName => assertions[].
     *
     * Supports two config formats:
     *   Flat:        [0 => 'route.name', 1 => 'route.other']
     *   Associative: ['route.name' => ['AssertionFQCN'], ...]
     *
     * @param  array<int|string, string|string[]> $list
     * @return array<string, string[]>
     */
    private function normalizeRouteList(array $list): array
    {
        $result = [];
        foreach ($list as $key => $value) {
            if (is_int($key)) {
                $result[$value] = [];
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
