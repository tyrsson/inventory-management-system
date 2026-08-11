<?php

declare(strict_types=1);

namespace Ims\Store;

use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\Admin\RequestHandler\UpdateUserHandler;
use Webware\UserManager\Admin\RequestHandler\UpdateUserModalHandler;
use Webware\UserManager\CommandHandler\CreateUserHandler;
use Webware\UserManager\Entity\User as WebwareUser;
use Webware\UserManager\InputFilter\UserDataFilter as WebwareUserDataFilter;

final class ConfigProvider
{
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

    public function getCommandMap(): array
    {
        return [
            // Entity\User::class => SaveUserHandler::class,
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                WebwareUser::class            => Entity\User::class,
                UpdateUserModalHandler::class => Http\Container\UpdateUserModalHandlerFactory::class,
            ],
        ];
    }

    public function getInputFilterConfig(): array
    {
        return [
            'factories' => [
                WebwareUserDataFilter::class => InputFilter\Container\UserDataFilterFactory::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            'paths' => [
                'ims-store' => [__DIR__ . '/../templates/user'],
            ],
        ];
    }

    public function __invoke(): array
    {
        return [
            'dependencies'             => $this->getDependencies(),
            'input_filters'            => $this->getInputFilterConfig(),
            'templates'                => $this->getTemplates(),
            AclInterface::class        => $this->getAclConfig(),
            AssertionManager::class    => $this->getAssertionManagerConfig(),
            CommandBusInterface::class => [
                'command_map' => $this->getCommandMap(),
            ],
        ];
    }
}
