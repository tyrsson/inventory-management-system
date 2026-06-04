<?php

declare(strict_types=1);

namespace Webware\Acl\Validator;

use Laminas\Validator;
use Override;
use Psr\Container\ContainerInterface;
use Webware\Acl\AssertionManager;

use function get_debug_type;
use function is_array;
use function is_string;
use function trim;

final class Assertion extends Validator\AbstractValidator
{
    public const NOT_EXIST        = 'notExist';

    public const INVALID_TYPE     = 'invalidType';

    public const NULL_NOT_ALLOWED = 'nullNotAllowed';

    protected array $messageVariables = [
        'assertion' => 'missingAssertion',
        'type'      => 'invalidType',
    ];

    protected array $messageTemplates = [
        self::NOT_EXIST        => 'Assertion %assertion% cannot be located by the AssertionManager',
        self::INVALID_TYPE     => 'Expected a string|string[], received: %type%',
        self::NULL_NOT_ALLOWED => 'To accept null, the "nullable" option must be set to true',
    ];

    private readonly bool $nullable;

    private string $missingAssertion;

    private string $invalidType;

    public function __construct(
        private readonly ContainerInterface&AssertionManager $assertionManager,
        ?array $options = ['nullable' => false]
    ) {
        $this->nullable = $options['nullable'];
        parent::__construct($options);
    }

    #[Override]
    public function isValid(mixed $value): bool
    {
        if (is_string($value) && trim($value) === '') {
            $value = null;
        }

        $this->setValue($value);

        if ($this->nullable && $value === null) {
            return true;
        }

        if (! $this->nullable && $value === null) {
            $this->error(self::NULL_NOT_ALLOWED);

            return false;
        }

        if (is_string($value) && trim($value) !== '') {
            if (! $this->assertionManager->has($value)) {
                $this->missingAssertion = $value;
                $this->error(self::NOT_EXIST);
                return false;
            }

            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_string($item) || trim($item) === '') {
                    $this->invalidType = get_debug_type($value);
                    $this->error(self::INVALID_TYPE);
                    return false;
                }
                if (! $this->assertionManager->has($item)) {
                    $this->missingAssertion = $item;
                    $this->error(self::NOT_EXIST);
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
