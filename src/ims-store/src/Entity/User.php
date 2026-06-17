<?php

declare(strict_types=1);

namespace Ims\Store\Entity;

use Ims\Store\Acl\StoreProprietaryInterface;
use Webware\UserManager\Entity\User as WebwareUser;

final class User extends WebwareUser implements StoreProprietaryInterface
{
    public private(set) string|int|null $storeId {
            get => $this->details['storeId'] ?? null;
            set => $this->details['storeId'] = $value;
    }

    public function getStoreId(): int|string|null
    {
        return $this->getDetail(self::STORE_ID_KEY);
    }

    public function withStoreId(int $storeId): self
    {
        return new self(
            id: $this->id,
            roleId: $this->roleId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            passwordHash: $this->passwordHash,
            active: $this->active,
            verificationToken: $this->verificationToken,
            tokenCreatedAt: $this->tokenCreatedAt,
            createdAt: $this->createdAt,
            details: [self::STORE_ID_KEY => $storeId],
        );
    }
}
