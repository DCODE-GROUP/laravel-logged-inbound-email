<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests\Unit\Models;

use Dcodegroup\LaravelLoggedInboundEmail\Models\InboundEmail;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

class InboundEmailTenantEnabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('inbound-email.multi_tenant_enabled', true);
        $app['config']->set('inbound-email.tenant_model', User::class);
    }

    public function test_tenant_id_column_is_present_when_enabled(): void
    {
        self::assertTrue(Schema::hasColumn('inbound_emails', 'tenant_id'));
    }

    public function test_tenant_relationship_resolves_configured_tenant_model(): void
    {
        $inboundEmail = InboundEmail::factory()->create(['tenant_id' => 1]);

        $relation = $inboundEmail->tenant();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(User::class, get_class($relation->getRelated()));
    }
}
