<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\RequestHandler;

use Axleus\Message\SystemMessengerInterface;
use DateTimeImmutable;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;

use function is_string;

final class VerifyEmailHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
        private readonly UserRepositoryInterface $users,
        private readonly int $tokenTtl,
        private readonly string $loginUrl,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tokenAttr = $request->getAttribute('token');
        $token     = is_string($tokenAttr) ? $tokenAttr : '';

        if ($token === '') {
            return new HtmlResponse(
                $this->template->render('user::verify-email', [
                    'error' => 'Invalid verification link.',
                ])
            );
        }

        $user = $this->users->findByVerificationToken($token);

        if ($user === null) {
            return new HtmlResponse(
                $this->template->render('user::verify-email', [
                    'error' => 'Invalid or already used verification link.',
                ])
            );
        }

        if ($user->tokenCreatedAt !== null) {
            $age = (new DateTimeImmutable())->getTimestamp() - $user->tokenCreatedAt->getTimestamp();

            if ($age > $this->tokenTtl) {
                return new HtmlResponse(
                    $this->template->render('user::verify-email', [
                        'error'   => 'Your verification link has expired.',
                        'expired' => true,
                    ])
                );
            }
        }

        $this->users->update($user->id, [
            'active'            => 1,
            'verificationToken' => null,
            'tokenCreatedAt'    => null,
        ]);

        /** @var SystemMessengerInterface|null $messenger */
        $messenger = $request->getAttribute(SystemMessengerInterface::class);
        $messenger?->success('Email verified! You may now sign in.', hops: 1, now: false);

        return new RedirectResponse($this->loginUrl);
    }
}
