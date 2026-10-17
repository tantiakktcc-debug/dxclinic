<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\TenantContext::reset();
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:30:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        \App\Services\TenantContext::reset();
        Carbon::setTestNow();
        parent::tearDown();
    }
}
