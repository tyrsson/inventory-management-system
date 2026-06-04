<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\ServiceManager\AbstractSingleInstancePluginManager;

use function array_keys;

final class AssertionManager extends AbstractSingleInstancePluginManager
{
    protected string $instanceOf = AssertionInterface::class;

    /** @var list<string> Alias keys collected from each registered config block. */
    private array $registeredAliases = [];

    public function configure(array $config): static
    {
        foreach (array_keys($config['aliases'] ?? []) as $alias) {
            $this->registeredAliases[] = $alias;
        }

        return parent::configure($config);
    }

    /**
     * Returns label/value pairs suitable for rendering assertion option cards.
     * Both 'label' and 'value' are the alias string (e.g. 'Ownership') which is
     * resolvable via AssertionManager::get().
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getAssertionOptions(): array
    {
        $options = [];
        foreach ($this->registeredAliases as $alias) {
            $options[] = ['label' => $alias, 'value' => $alias];
        }

        return $options;
    }
}
