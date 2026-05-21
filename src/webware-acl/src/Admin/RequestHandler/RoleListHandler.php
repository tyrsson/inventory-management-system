<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\RequestHandler;

use Htmx\Response\Header;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

use function json_encode;

/**
 * Handles GET /admin/access/roles — list all roles with parent info and user counts.
 * Handles POST /admin/access/roles — create a new role.
 *
 * TODO: implement POST (task 2.7).
 */
final class RoleListHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $roles       = [];
        $roleParents = [];

        // Build a set of role PKs that appear as a parent_pk in the inheritance
        // map. Used by the template to disable the delete button for parent roles.
        $rolesWithChildren = [];
        foreach ($roleParents as $parentPks) {
            foreach ($parentPks as $parentPk) {
                $rolesWithChildren[$parentPk] = true;
            }
        }

        $response = new HtmlResponse($this->template->render('acl::admin-roles', [
            'roles'             => $roles,
            'roleParents'       => $roleParents,
            'rolesWithChildren' => $rolesWithChildren,
        ]));

        $commandResult = $request->getAttribute(CommandResult::class);
        if ($commandResult instanceof CommandResult && $commandResult->getStatus() === CommandStatus::Success) {
            $response = $response->withHeader(Header::Trigger->value, json_encode(['closeModal' => null]));
        }

        return $response;
    }
}
