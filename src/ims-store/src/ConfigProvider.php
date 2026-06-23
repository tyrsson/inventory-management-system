<?php

declare(strict_types=1);

namespace Ims\Store;

use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\Command\SaveUserCommand;
use Webware\UserManager\Entity\User as WebwareUser;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'          => $this->getDependencies(),
            AclInterface::class     => $this->getAclConfig(),
            AssertionManager::class => $this->getAssertionManagerConfig(),
            CommandBusInterface::class => [
                'command_map' => $this->getCommandMap(),
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                WebwareUser::class => Entity\User::class,
            ],
        ];
    }

    public function getCommandMap(): array
    {
        return [
            // 'ims-store:import' => Command\ImportCommand::class,
            SaveUserCommand::class => CommandHandler\SaveUserCommandHandler::class,
        ];
    }

    public function getAclConfig(): array
    {
        return [
            // 'roles'     => [
            //     'Warehouse'            => ['Member'],
            //     'Sales'                => ['Member'],
            //     'Collections'          => ['Member'],
            //     'Warehouse Supervisor' => ['Warehouse'],
            //     'Assistant Manager'    => ['Sales', 'Warehouse', 'Collections'],
            //     'Manager'              => ['Assistant Manager', 'Warehouse Supervisor'],
            //     'Administrator'        => ['Manager'],
            // ],
            // 'resources' => [],
            // 'allow'     => [],
        ];
    }

    public function getAssertionManagerConfig(): array
    {
        return [
            'aliases'   => [
                'Store Owned Resource' => Acl\StoreOwnedResourceAssertion::class,
            ],
            'factories' => [
                Acl\StoreOwnedResourceAssertion::class => Acl\StoreOwnedResourceAssertion::class,
            ],
        ];
    }
}
