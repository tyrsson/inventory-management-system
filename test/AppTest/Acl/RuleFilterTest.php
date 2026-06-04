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

namespace AppTest\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Webware\Acl\RuleFilter;

#[CoversClass(RuleFilter::class)]
final class RuleFilterTest extends TestCase
{
    // ── valid cases ───────────────────────────────────────────────────────────

    #[Test]
    public function validBodyWithNullAssertionIsValid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => 'member',
            'resource_id' => 'product',
            'rule_type'   => 'Allow',
        ]));

        $this->assertTrue($filter->isValid());
        $this->assertSame('member', $filter->roleId);
        $this->assertSame('product', $filter->resourceId);
        $this->assertSame('Allow', $filter->type);
        $this->assertNull($filter->assertions);
    }

    #[Test]
    public function scalarAssertionAliasIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'         => 'member',
            'resource_id'     => 'product',
            'rule_type'       => 'Deny',
            'assertion_alias' => 'ownership',
        ]));

        $this->assertTrue($filter->isValid());
        $this->assertNull($filter->assertions);
    }

    #[Test]
    public function validBodyWithArrayAssertionPassesThrough(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'         => 'member',
            'resource_id'     => 'product',
            'rule_type'       => 'Allow',
            'assertion_alias' => ['ownership', 'store'],
        ]));

        $this->assertTrue($filter->isValid());
        $this->assertSame(['ownership', 'store'], $filter->assertions);
    }

    #[Test]
    public function emptyStringAssertionNormalisesToNull(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'         => 'member',
            'resource_id'     => 'product',
            'rule_type'       => 'Allow',
            'assertion_alias' => '',
        ]));

        $this->assertTrue($filter->isValid());
        $this->assertNull($filter->assertions);
    }

    #[Test]
    public function emptyArrayAssertionNormalisesToNull(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'         => 'member',
            'resource_id'     => 'product',
            'rule_type'       => 'Allow',
            'assertion_alias' => [],
        ]));

        $this->assertTrue($filter->isValid());
        $this->assertNull($filter->assertions);
    }

    #[Test]
    public function getValuesKeysMatchCommandConstructorParameters(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => 'admin',
            'resource_id' => 'ticket',
            'rule_type'   => 'Deny',
        ]));

        $this->assertSame(
            ['roleId', 'resourceId', 'type', 'assertions'],
            array_keys($filter->getValues())
        );
    }

    // ── failure cases ─────────────────────────────────────────────────────────

    #[Test]
    public function missingRoleIdIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'resource_id' => 'product',
            'rule_type'   => 'Allow',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->roleId);
    }

    #[Test]
    public function emptyRoleIdIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => '',
            'resource_id' => 'product',
            'rule_type'   => 'Allow',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->roleId);
    }

    #[Test]
    public function missingResourceIdIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'   => 'member',
            'rule_type' => 'Allow',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->resourceId);
    }

    #[Test]
    public function emptyResourceIdIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => 'member',
            'resource_id' => '',
            'rule_type'   => 'Allow',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->resourceId);
    }

    #[Test]
    public function missingRuleTypeIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => 'member',
            'resource_id' => 'product',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->type);
    }

    #[Test]
    public function unknownRuleTypeIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([
            'role_id'     => 'member',
            'resource_id' => 'product',
            'rule_type'   => 'SuperAllow',
        ]));

        $this->assertFalse($filter->isValid());
        $this->assertNull($filter->type);
    }

    #[Test]
    public function completelyEmptyBodyIsInvalid(): void
    {
        $filter = RuleFilter::fromRequest($this->makeRequest([]));

        $this->assertFalse($filter->isValid());
    }
    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeRequest(array $body): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }
}
