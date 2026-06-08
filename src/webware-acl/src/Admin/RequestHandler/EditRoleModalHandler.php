<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Admin\RequestHandler;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Repository\RoleRepository;

/**
 * Returns an HTML fragment containing the edit-role modal content.
 *
 * Intended for HTMX GET requests only. The response is swapped into
 * #sharedModalDialog, then the caller shows #sharedModal.
 */
final class EditRoleModalHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly TemplateRendererInterface $template,
        private readonly RoleRepository $roleRepository,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $roleId = $request->getAttribute('roleId', '');
        $roles  = $this->roleRepository->fetchAll();

        // Find the role being edited so we can pre-populate the form
        $role = null;
        foreach ($roles as $r) {
            if ($r->getRoleId() === $roleId) {
                $role = $r;
                break;
            }
        }

        return new HtmlResponse($this->template->render('acl::partials/edit-role-modal', [
            'role'   => $role,
            'roles'  => $roles,
            'layout' => false,
            'body'   => false,
        ]));
    }
}
