<?php

namespace Dcodegroup\LaravelLoggedInboundEmail\Tests;

abstract class OrganizationTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('inbound-email.organization_in_route', true);
    }
}
