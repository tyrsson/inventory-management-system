<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Container;

use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Laminas\Permissions\Acl\Assertion\AssertionAggregate;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Acl;
use Webware\Acl\AclInterface;

use function array_diff_key;
use function array_fill_keys;
use function array_is_list;
use function class_exists;
use function count;
use function is_a;

final readonly class AclFactory
{
    public function __invoke(ContainerInterface $container): Acl
    {
        $config   = $container->get('config')[AclInterface::class] ?? [];
        $laminas  = new LaminasAcl();

        $this->addRoles($laminas, $config['roles'] ?? []);
        $this->addResources($laminas, $config['resources'] ?? []);
        $this->applyRules($laminas, 'allow', $config['allow'] ?? []);
        $this->applyRules($laminas, 'deny',  $config['deny']  ?? []);

        return new Acl(acl: $laminas);
    }

    /**
     * Adds roles in topological order so every parent is registered before its children.
     *
     * @param array<string, string[]> $roles  roleId => parentRoleId[]
     */
    private function addRoles(LaminasAcl $acl, array $roles): void
    {
        $added   = [];
        $pending = $roles;

        $maxPasses = count($pending) + 1;
        $pass      = 0;

        while ($pending !== [] && $pass++ < $maxPasses) {
            foreach ($pending as $roleId => $parents) {
                if (array_diff_key(array_fill_keys($parents, true), $added) === []) {
                    $acl->addRole($roleId, $parents ?: null);
                    $added[$roleId] = true;
                    unset($pending[$roleId]);
                }
            }
        }
    }

    /**
     * Adds resources to the ACL. Resources are a flat list of route name strings.
     *
     * @param string[] $resources
     */
    private function addResources(LaminasAcl $acl, array $resources): void
    {
        foreach ($resources as $resourceId) {
            $acl->addResource((string) $resourceId);
        }
    }

    /**
     * Applies allow or deny rules from the config.
     *
     * Two formats are supported per role:
     *   list:         ['resource.a', 'resource.b']           — no assertions
     *   associative:  ['resource.a' => [AssertionFqcn::class]] — with assertions
     *
     * @param array<string, list<string>|array<string, string[]>> $rules
     */
    private function applyRules(LaminasAcl $acl, string $type, array $rules): void
    {
        foreach ($rules as $roleId => $resources) {
            if (array_is_list($resources)) {
                // Flat list — no assertions
                foreach ($resources as $resourceId) {
                    if ($type === 'allow') {
                        $acl->allow($roleId, (string) $resourceId, null);
                    } else {
                        $acl->deny($roleId, (string) $resourceId, null);
                    }
                }
            } else {
                // Associative — resource => FQCN[] with assertions
                foreach ($resources as $resourceId => $fqcns) {
                    $assertion = $this->buildAssertion((array) $fqcns);
                    if ($type === 'allow') {
                        $acl->allow($roleId, $resourceId, null, $assertion);
                    } else {
                        $acl->deny($roleId, $resourceId, null, $assertion);
                    }
                }
            }
        }
    }

    /**
     * Instantiates AssertionInterface objects from a list of FQCNs.
     * Returns null for no assertions, the single instance for one, or an
     * AssertionAggregate (MODE_AT_LEAST_ONE) for multiple.
     *
     * @param string[] $fqcns
     */
    private function buildAssertion(array $fqcns): ?AssertionInterface
    {
        $instances = [];
        foreach ($fqcns as $fqcn) {
            if (class_exists($fqcn) && is_a($fqcn, AssertionInterface::class, true)) {
                $instances[] = new $fqcn();
            }
        }

        if ($instances === []) {
            return null;
        }

        if (count($instances) === 1) {
            return $instances[0];
        }

        $aggregate = new AssertionAggregate();
        $aggregate->setMode(AssertionAggregate::MODE_AT_LEAST_ONE);
        foreach ($instances as $instance) {
            $aggregate->addAssertion($instance);
        }

        return $aggregate;
    }
}
