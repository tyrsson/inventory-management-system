<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Returns an HTML fragment containing the delete-rule modal content.
 *
 * Intended for HTMX GET requests only. The response is swapped into the
 * persistent #deleteRuleModalContent shell in the layout (outside <main>),
 * then the caller shows the Bootstrap modal programmatically.
 */
final class DeleteRuleModalHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $roleId     = $request->getAttribute('roleId', '');
        $resourceId = $request->getAttribute('resourceId', '');

        return new HtmlResponse($this->template->render('acl::partials/delete-rule-modal', [
            'roleId'     => $roleId,
            'resourceId' => $resourceId,
            'layout'     => false,
            'body'       => false,
        ]));
    }
}
