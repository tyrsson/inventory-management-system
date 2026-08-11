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

namespace Webware\UserManager\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use SensitiveParameter;
use Webware\CommandBus\Command\NamedCommandInterface;
use Webware\CommandBus\Command\NamedCommandTrait;
use Webware\UserManager\UserInterface;

use function json_encode;
use function json_validate;
use function password_get_info;
use function password_hash;

class CreateUserCommand implements NamedCommandInterface
{
    use NamedCommandTrait;

    /** @param string[] $roleId */
    public function __construct(
        public private(set) string $firstName {
            get => $this->firstName;
            set(string $value) {
                $this->firstName = $value;
            }
        },
        public private(set) string $lastName {
            get => $this->lastName;
            set(string $value) {
                $this->lastName = $value;
            }
        },
        #[SensitiveParameter]
        public private(set) string $passwordHash {
            get => $this->passwordHash;
            set(string $value) {
                if (password_get_info($value)['algo'] === null) {
                    $this->passwordHash = password_hash($value, PASSWORD_DEFAULT);
                } else {
                    $this->passwordHash = $value;
                }
            }
        },
        public private(set) string $email {
            get => $this->email;
            set(string $value) {
                $this->email = strtolower($value);
            }
        },
        public private(set) array|string $roleId {
            get => $this->roleId;
            set(array|string $value) {
                if (is_array($value)) {
                    $this->roleId = json_encode($value);
                } elseif (is_string($value) && json_validate($value)) {
                    $this->roleId = $value;
                } else {
                    throw new InvalidArgumentException('roleId must be a valid JSON string or an array.');
                }
            }
        },
        public private(set) string $verificationToken {
            get => $this->verificationToken;
            set(string $value) {
                $this->verificationToken = $value;
            }
        },
        public private(set) bool $active = false {
            get => $this->active;
            set(bool $value) {
                $this->active = $value;
            }
        },
        public private(set) DateTimeImmutable|string $tokenCreatedAt = new DateTimeImmutable() {
            get => $this->tokenCreatedAt;
            set(DateTimeImmutable|string $value) {
                if ($value instanceof DateTimeImmutable) {
                    $this->tokenCreatedAt = $value->format(UserInterface::DATETIME_FORMAT);
                } elseif (is_string($value)) {
                    $this->tokenCreatedAt = new DateTimeImmutable($value)->format(UserInterface::DATETIME_FORMAT);
                } else {
                    throw new InvalidArgumentException(
                        'tokenCreatedAt must be a DateTimeImmutable object or a valid date string.',
                    );
                }
            }
        },
        public private(set) DateTimeImmutable|string $createdAt = new DateTimeImmutable() {
            get => $this->createdAt;
            set(DateTimeImmutable|string $value) {
                if ($value instanceof DateTimeImmutable) {
                    $this->createdAt = $value->format(UserInterface::DATETIME_FORMAT);
                } elseif (is_string($value)) {
                    $this->createdAt = new DateTimeImmutable($value)->format(UserInterface::DATETIME_FORMAT);
                } else {
                    throw new InvalidArgumentException(
                        'createdAt must be a DateTimeImmutable object or a valid date string.',
                    );
                }
            }
        },
    ) {}
}
