<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected string $environmentFilePath;

    protected function setUp(): void
    {
        $this->environmentFilePath = dirname(__DIR__) . '/.env.testing';

        if (file_exists($this->environmentFilePath)) {
            unlink($this->environmentFilePath);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->environmentFilePath)) {
            unlink($this->environmentFilePath);
        }

        parent::tearDown();
    }
}
