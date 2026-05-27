<?php

declare(strict_types=1);

namespace Webware\Acl\RequestHandler;

use Axleus\Message\SystemMessengerInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webware\UserManager\UserInterface;

final readonly class ForbiddenHandler implements ForbiddenHandlerInterface
{
    public function __construct(
        private string  $loginPath         = '/login',
        private ?string $forbiddenRedirect = '/',
        private ?string $forbiddenTemplate = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);

        // Guest identity — silent redirect to login, no toast
        if ($user->isGuest()) {
            return new RedirectResponse($this->loginPath);
        }

        // Authenticated but denied — conditional toast then redirect
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $messenger?->warning(
            'You do not have permission to access the requested resource.',
            hops: 1,
            now: false,
        );

        $serverParams = $request->getServerParams();
        $redirect     = $this->forbiddenRedirect
            ?? $serverParams['HTTP_REFERER']
            ?? '/';

        return new RedirectResponse($redirect);
    }
}
