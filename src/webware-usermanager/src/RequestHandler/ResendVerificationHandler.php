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

use Axleus\Mailer\MailerInterface;
use DateTimeImmutable;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\UserInterface;
use Webware\UserManager\View\Helper\UserUrl;

use function htmlspecialchars;
use function is_array;
use function is_string;
use function rtrim;

use const ENT_QUOTES;

final class ResendVerificationHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
        private readonly UserRepositoryInterface $users,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly string $baseUrl,
        private readonly string $verificationSubject,
        private readonly string $loginUrl,
        private readonly UserUrl $userUrl,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            return new HtmlResponse($this->template->render('user::resend-verification'));
        }

        $body  = $request->getParsedBody();
        $email = is_array($body) && is_string($body['email'] ?? null) ? $body['email'] : '';

        if ($email === '') {
            return new HtmlResponse(
                $this->template->render('user::resend-verification', [
                    'error' => 'Please enter a valid email address.',
                ]),
            );
        }

        $user = $this->users->findByEmail($email);

        // Already-active users have no business here — send them to login.
        if ($user !== null && $user->active === true) {
            return new RedirectResponse($this->loginUrl);
        }

        // Silently skip unknown emails — do not reveal whether the address is registered
        // (prevents user enumeration). The "check your inbox" page is always shown.
        if ($user !== null) {
            $token = Uuid::uuid7()->toString();
            $now   = new DateTimeImmutable()->format(UserInterface::DATETIME_FORMAT);

            $updated = $this->users->update($user->id, [
                'verificationToken' => $token,
                'tokenCreatedAt'    => $now,
            ]);

            $verificationUrl =
                rtrim($this->baseUrl, '/')
                . ($this->userUrl)('verify.email.read', ['token' => $token]);
            $adapter = $updated > 0 ? $this->mailer->getAdapter() : null;

            if ($adapter !== null) {
                $adapter->from($this->fromEmail, $this->fromName)
                    ->to($email, $user->firstName . ' ' . $user->lastName)
                    ->subject($this->verificationSubject)
                    ->isHtml(true)
                    ->body(
                        '<p>Hello '
                        . htmlspecialchars($user->firstName, ENT_QUOTES, 'UTF-8')
                        . ',</p>'
                        . '<p>You requested a new verification link. Please verify your email address by clicking below.</p>'
                        . '<p><a href="'
                        . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8')
                        . '">Verify my email</a></p>'
                        . '<p>This link expires in 24 hours.</p>',
                    )
                    ->altBody(
                        'Hello '
                        . $user->firstName
                        . ",\n\n"
                        . "You requested a new verification link. Please visit:\n"
                        . $verificationUrl
                        . "\n\n"
                        . "This link expires in 24 hours.\n",
                    );

                $this->mailer->send();
            }
        }

        return new HtmlResponse(
            $this->template->render('user::resend-verification', ['sent' => true]),
        );
    }
}
