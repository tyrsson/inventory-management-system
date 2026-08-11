<?php

declare(strict_types=1);

namespace Webware\Core\InputFilter;

use function array_merge;
use function implode;
use function is_array;
use function json_encode;

trait SystemMessageTrait
{
    public function getSystemMessage(bool $asJson = false): string
    {
        if ($asJson) {
            return json_encode($this->getMessages()->jsonSerialize());
        }

        return implode('<br />', $this->flattenMessages($this->getMessages()->toArray()));
    }

    /**
     * Recursively flatten nested InputFilter error messages (e.g. from fieldsets,
     * or Laminas's own validator-key nesting) into a flat list of "field: message"
     * strings, so the user can tell which field a message belongs to.
     *
     * @param array<array-key, mixed> $messages
     * @return string[]
     */
    private function flattenMessages(array $messages, ?string $key = null): array
    {
        $flattened = [];
        foreach ($messages as $field => $message) {
            $label = null !== $key ? $key . '.' . $field : (string) $field;

            if (is_array($message)) {
                if ($this->isLeafMessageSet($message)) {
                    foreach ($message as $text) {
                        $flattened[] = $label . ': ' . $text;
                    }
                } else {
                    $flattened = array_merge($flattened, $this->flattenMessages($message, $label));
                }
            } else {
                $flattened[] = $label . ': ' . $message;
            }
        }

        return $flattened;
    }

    /**
     * A "leaf" message set is a validator-key => message string map
     * (e.g. `['isEmpty' => 'Value is required...']`) with no further nesting.
     *
     * @param array<array-key, mixed> $message
     */
    private function isLeafMessageSet(array $message): bool
    {
        foreach ($message as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }
}
