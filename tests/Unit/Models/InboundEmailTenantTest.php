<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Unit\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use LogicException;

class InboundEmailTenantTest extends TestCase
{
    public function test_tenant_id_column_is_absent_by_default(): void
    {
        self::assertFalse(Schema::hasColumn('inbound_emails', 'tenant_id'));
    }

    public function test_tenant_relationship_throws_when_multi_tenancy_is_disabled(): void
    {
        $inboundEmail = InboundEmail::factory()->create();

        $this->expectException(LogicException::class);

        $inboundEmail->tenant();
    }
}
