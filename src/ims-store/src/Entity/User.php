<?php

declare(strict_types=1);

namespace Ims\Store\Entity;

use Ims\Store\Acl\StoreProprietaryInterface;
use Webware\UserManager\Entity\User as WebwareUser;

final class User extends WebwareUser implements StoreProprietaryInterface
{
    public string|int|null $storeId {
        get => $this->details[self::STORE_ID_KEY] ?? null;
    }

    public function getStoreId(): int|string|null
    {
        return $this->getDetail(self::STORE_ID_KEY);
    }

    public function withStoreId(int $storeId): self
    {
        return $this->withDetail(self::STORE_ID_KEY, $storeId);
    }
}
