<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Middleware;

use Fig\Http\Message\RequestMethodInterface as HttpMethod;
use Laminas\Permissions\Acl\Acl;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;
use Webware\Acl\Entity\Role;
use Webware\Acl\PrivilegeInterface;
use Webware\Acl\Repository\RuleRepository;
use Webware\Acl\RuleType;

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
final readonly class OverviewMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RuleRepository $ruleRepository,
        private RouteCollectorInterface $routeCollector,
        private AssertionManager $assertionManager,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Acl&AclInterface $acl */
        $acl      = $request->getAttribute(AclInterface::class);
        $allRules = $this->ruleRepository->fetchAll();

        $configAllow      = [];
        $configDeny       = [];
        $parentResourceMap = [];
        foreach ($allRules as $rule) {
            if (RuleType::from($rule['type']) === RuleType::Allow) {
                $configAllow[$rule['roleId']][$rule['resourceId']] = $rule['assertions'];
            } else {
                $configDeny[$rule['roleId']][$rule['resourceId']] = [];
            }
            if ($rule['parentResourceId'] !== null) {
                $parentResourceMap[$rule['resourceId']] = $rule['parentResourceId'];
            }
        }

        $assertionOptions = $this->assertionManager->getAssertionOptions();

        $configRoles = $acl->getRoles();
        $roleNames   = array_keys($configRoles);
        $rolePkMap   = array_flip($roleNames);

        $unprotectedRoutes = [];
        $protectedRoutes   = [];

        foreach ($this->routeCollector->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }

            $methods = $route->getAllowedMethods() ?? [HttpMethod::METHOD_GET];

            if (! $acl->hasResource($name)) {
                $unprotectedRoutes[$name] = $methods;

                continue;
            }

            $inheritedFrom = $parentResourceMap[$name] ?? null;
            $lookupName    = $inheritedFrom ?? $name;
            $rules           = [];
            $rolesOnResource = [];
            $syntheticId     = 0;
            $hasAssertions   = false;

            foreach ($configAllow as $roleId => $allowedRoutes) {
                $normalized = $this->normalizeRouteList($allowedRoutes);
                if (isset($normalized[$lookupName])) {
                    $assertions = array_values(array_unique($normalized[$lookupName]));
                    $rules[]    = [
                        'id'             => ++$syntheticId,
                        'roleId'         => $roleId,
                        'resourceId'     => $name,
                        'privilege_id'   => '',
                        'type'           => RuleType::Allow->value,
                        'assertions'     => $assertions,
                        'inherited'      => $inheritedFrom !== null,
                        'inherited_from' => $inheritedFrom,
                    ];
                    $rolesOnResource[$roleId] = true;
                    if ($assertions !== []) {
                        $hasAssertions = true;
                    }
                }
            }

            foreach ($configDeny as $roleId => $deniedRoutes) {
                $normalized = $this->normalizeRouteList($deniedRoutes);
                if (isset($normalized[$lookupName])) {
                    $rules[] = [
                        'id'             => ++$syntheticId,
                        'roleId'         => $roleId,
                        'resourceId'     => $name,
                        'privilege_id'   => '',
                        'type'           => RuleType::Deny->value,
                        'assertions'     => [],
                        'inherited'      => $inheritedFrom !== null,
                        'inherited_from' => $inheritedFrom,
                    ];
                    $rolesOnResource[$roleId] = true;
                }
            }

            $derivedPrivileges = array_values(array_unique(array_map(
                static fn (string $m): string => PrivilegeInterface::METHOD_PRIVILEGE_MAP[$m] ?? PrivilegeInterface::READ,
                $methods,
            )));

            if ($rules === []) {
                $unprotectedRoutes[$name] = $methods;

                continue;
            }

            $protectedRoutes[$name] = [
                'methods'           => $methods,
                'derivedPrivileges' => $derivedPrivileges,
                'ruleCount'         => count($rules),
                'roles'             => array_keys($rolesOnResource),
                'hasAssertions'     => $hasAssertions,
                'rules'             => $rules,
                'resourcePk'        => 0,
            ];
        }

        $roles       = [];
        $roleParents = [];

        foreach ($roleNames as $pk => $roleId) {
            $roles[$pk] = new Role(roleId: $roleId);
            $parentPks  = [];
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
            'roles'             => $roles,
            'roleParents'       => $roleParents,
            'assertions'        => $assertionOptions,
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
     * @param array<int|string, string|string[]> $list
     * @return array<string, string[]>
     */
    private function normalizeRouteList(array $list): array
    {
        $result = [];
        foreach ($list as $key => $value) {
            if (is_int($key)) {
                $result[$value] = [];
            } else {
                $result[$key] = $value ?? [];
            }
        }

        return $result;
    }
}
