<?php

declare(strict_types=1);

namespace Webware\UserManager\Admin\Dashboard;

use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\ResultSet\WithRowDataResultSet;
use Webware\UserManager\Entity\User;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class RegisterWidgetListener
{
    public function __construct(
        private readonly string $resourceId,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function __invoke(RegisterWidgetEvent $event): void
    {
        /** @var User[] $allUsers */
        $allUsers      = $this->users->findAll() ?? [];
        $totalUsers    = count($allUsers);
        $activeUsers   = 0;
        $inactiveUsers = 0;

        foreach ($allUsers as $user) {
            if ($user->active) {
                $activeUsers++;
            } else {
                $inactiveUsers++;
            }
        }

        $event->registerWidget(new Widget(
            resourceId: $this->resourceId,
            totalUsers: $totalUsers,
            activeUsers: $activeUsers,
            inactiveUsers: $inactiveUsers,
        ));
    }
}
