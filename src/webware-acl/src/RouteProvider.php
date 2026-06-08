<?php

declare(strict_types=1);

namespace Webware\Acl;

use Htmx\Middleware\DisableBodyMiddleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouteProviderInterface;
use Override;
use Webware\Acl\Admin\Middleware\OverviewMiddleware;
use Webware\Acl\Admin\Middleware\ProcessRoleMiddleware;
use Webware\Acl\Admin\Middleware\ProcessRuleMiddleware;
use Webware\Acl\Admin\RequestHandler\AclOverviewHandler;
use Webware\Acl\Admin\RequestHandler\AddRoleModalHandler;
use Webware\Acl\Admin\RequestHandler\DeleteRuleModalHandler;
use Webware\Acl\Admin\RequestHandler\EditRoleModalHandler;
use Webware\Acl\Admin\RequestHandler\ResourceListHandler;
use Webware\Acl\Admin\RequestHandler\RoleListHandler;

use function rtrim;

final readonly class RouteProvider implements RouteProviderInterface
{
    public function __construct(
        private string $adminRouteSegment,
        private string $adminRouteNamePrefix,
    ) {}

    #[Override]
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        /*
         * ACL Manager — Component route / resource mappings
         *
         * Abstract:
         * This is the components base "manager" route. It is the resource
         * that all other acl actions are children of. It will have a "read" privilege
         * assigned to it. All other routes (roles, resources, rules) are children of this route and will
         * have their own privileges (create/update/delete) but not "read" since they are not accessed directly,
         * but rather as part of the ACL managers workflow.
         *
         * "ACL Manager"
         * /webware.admin/acl.manager (GET) read
         * route name / resourceId {route_prefix admin.}acl.manager
         *
         * ------------------------------------------------------------------------------
         *
         * "Sub Resource - child route" Role
         * /webware.admin/acl.manager/role (GET) read
         * route name / resourceId {route_prefix admin.}acl.manager.role.list
         *
         * List of roles, returns template fragment
         * showing all roles etc. This could be part of the ACL Manager page but having it as a separate
         * route allows for better separation of concerns and more flexibility in the
         * template layer (e.g. htmx can target this route to update just the roles list without reloading the entire ACL Manager page).
         *
         * "Sub Resource - child route" Role CRUD
         *
         * /webware.admin/acl.manager/role/{role_id} (PATCH/PUT) update
         * route name / resourceId {route_prefix admin.}acl.manager.update.role
         *
         * /webware.admin/acl.manager/role (POST) create
         * route name / resourceId {route_prefix admin.}acl.manager.create.role
         *
         * /webware.admin/acl.manager/role/{role_id} (DELETE) delete
         * route name / resourceId {route_prefix admin.}acl.manager.delete.role
         */
        $routeCollector->get(
            '/' . $this->adminRouteSegment,
            $middlewareFactory->prepare(
                [
                    OverviewMiddleware::class,
                    AclOverviewHandler::class,
                ]
            ),
            rtrim($this->adminRouteNamePrefix, '.')
        )->setOptions([
            'navigation' => 'admin',
            'label'      => 'Access Control',
            'icon'       => 'bi-shield-lock',
            'parent'     => null,
            'order'      => 15,
        ]);

        // Role management
        $routeCollector->get(
            '/' . $this->adminRouteSegment . '/roles',
            $middlewareFactory->prepare(
                [
                    RoleListHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'role.read'
        )->setOptions([
            'label'  => 'Roles',
            'icon'   => 'bi-shield-lock-fill',
            'parent' => $this->adminRouteNamePrefix . 'acl.read',
            'order'  => 30,
        ]);

        // Resource management
        // $routeCollector->get(
        //     '/' . $this->adminRouteSegment . '/acl.manager/resources',
        //     $middlewareFactory->prepare([ResourceListHandler::class]),
        //     $this->adminRouteNamePrefix . 'acl.resources.read'
        // )->setOptions([
        //     'label'  => 'Resources',
        //     'icon'   => 'bi-file-earmark-lock-fill',
        //     'parent' => $this->adminRouteNamePrefix . 'acl.read',
        //     'order'  => 40,
        // ]);

        // // Rule management
        // $routeCollector->get(
        //     '/' . $this->adminRouteSegment . '/acl.manager/rules',
        //     $middlewareFactory->prepare([RuleManagerHandler::class]),
        //     $this->adminRouteNamePrefix . 'acl.rules.read'
        // )->setOptions([
        //     'label'  => 'Rules',
        //     'icon'   => 'bi-list-check',
        //     'parent' => $this->adminRouteNamePrefix . 'acl.read',
        //     'order'  => 50,
        // ]);

        // Rules write/delete
        $routeCollector->post(
            '/' . $this->adminRouteSegment . '/rule',
            $middlewareFactory->prepare(
                [
                    ProcessRuleMiddleware::class,
                    OverviewMiddleware::class,
                    AclOverviewHandler::class,
                ]
            ),
            $this->adminRouteNamePrefix . 'rule.create'
        );

        $routeCollector->patch(
            '/' . $this->adminRouteSegment . '/rule',
            $middlewareFactory->prepare(
                [
                    BodyParamsMiddleware::class,
                    ProcessRuleMiddleware::class,
                    OverviewMiddleware::class,
                    AclOverviewHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'rule.update'
        );

        $routeCollector->delete(
            '/' . $this->adminRouteSegment . '/rule/{roleId:[^/]+}/{resourceId:[^/]+}',
            $middlewareFactory->prepare(
                [
                    ProcessRuleMiddleware::class,
                    OverviewMiddleware::class,
                    AclOverviewHandler::class,
                ]
            ),
            $this->adminRouteNamePrefix . 'rule.delete'
        );

        $routeCollector->get(
            '/' . $this->adminRouteSegment . '/rule/{roleId:[^/]+}/{resourceId:[^/]+}/modal',
            $middlewareFactory->prepare(
                [
                    DisableBodyMiddleware::class,
                    DeleteRuleModalHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'rule.delete.modal'
        );

        // Roles write/delete
        $routeCollector->post(
            '/' . $this->adminRouteSegment . '/role',
            $middlewareFactory->prepare(
                [
                    ProcessRoleMiddleware::class,
                    RoleListHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'role.create'
        );

        $routeCollector->get(
            '/' . $this->adminRouteSegment . '/role/modal',
            $middlewareFactory->prepare(
                [
                    DisableBodyMiddleware::class,
                    AddRoleModalHandler::class,
                ]
            ),
            $this->adminRouteNamePrefix . 'role.add.modal'
        );

        $routeCollector->get(
            '/' . $this->adminRouteSegment . '/role/{roleId:[^/]+}/modal',
            $middlewareFactory->prepare(
                [
                    DisableBodyMiddleware::class,
                    EditRoleModalHandler::class,
                ]
            ),
            $this->adminRouteNamePrefix . 'role.edit.modal'
        );

        $routeCollector->patch(
            '/' . $this->adminRouteSegment . '/role/{roleId:[^/]+}',
            $middlewareFactory->prepare(
                [
                    BodyParamsMiddleware::class,
                    ProcessRoleMiddleware::class,
                    RoleListHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'role.update'
        );

        $routeCollector->delete(
            '/' . $this->adminRouteSegment . '/role/{roleId:[^/]+}',
            $middlewareFactory->prepare(
                [
                    ProcessRoleMiddleware::class,
                    RoleListHandler::class
                ]
            ),
            $this->adminRouteNamePrefix . 'role.delete'
        );

        // Resources write/delete routes removed — resources are route-derived
        // and cannot be manually created or deleted via the UI.
    }
}
