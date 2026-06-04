<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler;

use Htmx\Response\Header;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

use function json_encode;

/**
 * Handles GET /admin/access/resources — list all resources with their privileges.
 * Handles POST /admin/access/resources — create a new resource.
 *
 * Route protection for unprotected routes is handled by the Access Control page
 * (admin.acl.read). This handler focuses on low-level resource CRUD only.
 */
final class ResourceListHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $resources  = $this->config['resources'] ?? [];
        $privileges = [];
        $roles      = [];

        $response = new HtmlResponse($this->template->render('acl::admin-resources', [
            'resources'  => $resources,
            'privileges' => $privileges,
            'roles'      => $roles,
        ]));

        $commandResult = $request->getAttribute(CommandResult::class);
        if ($commandResult instanceof CommandResult && $commandResult->getStatus() === CommandStatus::Success) {
            $response = $response->withHeader(Header::Trigger->value, json_encode(['closeModal' => null]));
        }

        return $response;
    }
}
