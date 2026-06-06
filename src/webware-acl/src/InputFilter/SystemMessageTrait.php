<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter;

trait SystemMessageTrait
{
    public function getSystemMessage(bool $asJson = false): string
    {
        if ($asJson) {
            return json_encode($this->getMessages()->jsonSerialize());
        }

        return implode('<br />', $this->getMessages()->toArray());
    }
}
