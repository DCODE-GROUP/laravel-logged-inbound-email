<?php

use Dcodegroup\LaravelLoggedInboundEmail\Tests\OrganizationTestCase;
use Dcodegroup\LaravelLoggedInboundEmail\Tests\TestCase;

uses(TestCase::class)->in('Feature/*.php', 'Unit');
uses(OrganizationTestCase::class)->in('Feature/Organization');
