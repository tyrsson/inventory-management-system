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

namespace Webware\UserManager\Admin\RequestHandler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class ToggleUserActiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id   = (int) $request->getAttribute('id');
        $user = $this->users->findById($id);

        if ($user === null) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $this->users->update($id, [
            'active' => $user->active ? 0 : 1,
        ]);

        return new JsonResponse(['active' => ! $user->active]);
    }
}
