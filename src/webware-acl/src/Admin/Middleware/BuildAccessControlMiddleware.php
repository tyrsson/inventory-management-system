<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\Middleware;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Entity\Privilege;

use function array_keys;
use function array_map;
use function count;

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
 *   unprotectedRoutes  array<string, string[]>            routeName → allowedMethods
 *   protectedRoutes    array<string, array{...}>           routeName → summary
 *   roleTree           array<int, array{...}>              rolePk → {id, roleId, parents}
 *   roleChildren       array<int, int[]>                   parentPk → childPks
 *   routeFilters       array{all:int, unprotected:int, protected:int}
 *   roles              array<int, Role>
 *   roleParents        array<int, int[]>
 */
final class BuildAccessControlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly array $config,
        private readonly RouteCollectorInterface $routeCollector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resources   = $this->config['resources'] ?? [];
        $privileges  = [];
        $rules       = $this->config['rules'] ?? [];
        $assertions  = $this->config['assertions'] ?? [];
        $roles       = [];
        $roleParents = [];

        // resourceId → resourcePk
        $registeredIds = [];
        foreach ($resources as $pk => $resource) {
            $registeredIds[$resource->resourceId] = $pk;
        }

        // resourcePk → Privilege[]
        $privilegesByResource = [];
        foreach ($privileges as $priv) {
            $privilegesByResource[$priv->resourcePk][] = $priv;
        }

        // resourceId → rule row[]
        $rulesByResource = [];
        foreach ($rules as $rule) {
            $rulesByResource[$rule['resource_id']][] = $rule;
        }

        $unprotectedRoutes = [];
        $protectedRoutes   = [];

        foreach ($this->routeCollector->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }

            if (! isset($registeredIds[$name])) {
                $unprotectedRoutes[$name] = $route->getAllowedMethods() ?? ['GET'];
                continue;
            }

            $resourcePk    = $registeredIds[$name];
            $privs         = $privilegesByResource[$resourcePk] ?? [];
            $resourceRules = $rulesByResource[$name] ?? [];

            $rolesOnResource = [];
            $hasAssertions   = false;
            foreach ($resourceRules as $rule) {
                $rolesOnResource[$rule['role_id']] = true;
                if (! empty($assertions[$rule['id']] ?? [])) {
                    $hasAssertions = true;
                }
            }

            // Enrich each rule row with its assertion data
            $enrichedRules = [];
            foreach ($resourceRules as $rule) {
                $enrichedRules[] = $rule + ['assertions' => $assertions[$rule['id']] ?? []];
            }

            $protectedRoutes[$name] = [
                'methods'           => $route->getAllowedMethods() ?? ['GET'],
                'derivedPrivileges' => array_map(static fn(Privilege $p): string => $p->privilegeId, $privs),
                'ruleCount'         => count($resourceRules),
                'roles'             => array_keys($rolesOnResource),
                'hasAssertions'     => $hasAssertions,
                'rules'             => $enrichedRules,
                'resourcePk'        => $resourcePk,
            ];
        }

        // Role tree for the wizard role-selection step
        $roleTree = [];
        foreach ($roles as $pk => $role) {
            $roleTree[$pk] = [
                'id'      => $pk,
                'roleId'  => $role->roleId,
                'parents' => $roleParents[$pk] ?? [],
            ];
        }

        // parentPk → childPk[] for tree rendering
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
        ];

        return $handler->handle($request->withAttribute(self::class, $viewModel));
    }
}
