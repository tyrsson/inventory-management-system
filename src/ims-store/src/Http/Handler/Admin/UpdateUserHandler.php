<?php

declare(strict_types=1);

namespace Ims\Store\Http\Handler\Admin;

use Ims\Store\Entity\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psl\Type;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class UpdateUserHandler implements RequestHandlerInterface
{
    public function __construct(
        private TemplateRendererInterface $template,
        private UserRepositoryInterface $userRepository,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Implementation will go here
        return new HtmlResponse($this->template->render('user::list-users', [
            'users' => $request->getAttribute('updatedUsers', []),
        ]));
    }
}
