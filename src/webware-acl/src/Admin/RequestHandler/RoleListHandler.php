<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler;

use Htmx\Response\Header;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Repository\RoleRepository;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

use function json_encode;

final class RoleListHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
        private readonly RoleRepository $roleRepository,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $roles = $this->roleRepository->fetchAll();

        // Build a set of roleIds that appear as a parent in any role's parentId.
        // Used by the template to disable the delete button for roles that have children.
        $rolesWithChildren = [];
        foreach ($roles as $role) {
            foreach ($role->parentId ?? [] as $parent) {
                $rolesWithChildren[$parent->getRoleId()] = true;
            }
        }

        $response = new HtmlResponse($this->template->render('acl::admin-roles', [
            'roles'             => $roles,
            'rolesWithChildren' => $rolesWithChildren,
        ]));

        $commandResult = $request->getAttribute(CommandResult::class);
        if ($commandResult instanceof CommandResult && $commandResult->getStatus() === CommandStatus::Success) {
            $response = $response->withHeader(Header::Trigger->value, json_encode(['closeModal' => null]));
        }

        return $response;
    }
}
