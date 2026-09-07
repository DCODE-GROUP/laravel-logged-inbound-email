<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Unit\Support;

use Dcodegroup\LaravelLoggedInboundEmail\Support\PlusAddressTenantResolver;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

class PlusAddressTenantResolverTest extends TestCase
{
    private PlusAddressTenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PlusAddressTenantResolver;
    }

    public function test_extracts_tenant_identifier_before_plus(): void
    {
        $result = $this->resolver->resolve([['email' => 'acme-corp+support@example.com', 'name' => null]]);

        self::assertSame('acme-corp', $result);
    }

    public function test_returns_null_when_no_plus_segment(): void
    {
        $result = $this->resolver->resolve([['email' => 'support@example.com', 'name' => null]]);

        self::assertNull($result);
    }

    public function test_returns_null_when_local_part_starts_with_plus(): void
    {
        $result = $this->resolver->resolve([['email' => '+support@example.com', 'name' => null]]);

        self::assertNull($result);
    }

    public function test_returns_null_when_no_recipients(): void
    {
        self::assertNull($this->resolver->resolve([]));
    }
}
