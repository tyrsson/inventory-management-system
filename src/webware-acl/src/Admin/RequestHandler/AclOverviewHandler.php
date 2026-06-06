<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Middleware\OverviewMiddleware;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

/**
 * Handles GET /admin/access — route-centric Access Control page.
 *
 * All data assembly is performed by BuildAccessControlMiddleware which runs
 * before this handler in the pipeline and attaches the view model as a request
 * attribute. This handler is render-only.
 */
final class AclOverviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $viewModel */
        $viewModel = $request->getAttribute(OverviewMiddleware::class, []);

        $response = new HtmlResponse($this->template->render('acl::admin-acl', $viewModel));

        // Close the wizard modal after a successful POST
        $result = $request->getAttribute(CommandResult::class);
        if ($result instanceof CommandResult && $result->getStatus() === CommandStatus::Success) {
            $response = $response->withHeader('HX-Trigger', 'closeModal');
        }

        return $response;
    }
}
