<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $botToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botToken = '123:ABC';
        config(['traffic_source.settings.telegram.token' => $this->botToken]);
    }
}
