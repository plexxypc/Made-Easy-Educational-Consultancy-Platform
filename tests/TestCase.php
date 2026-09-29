<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests must not depend on `npm run build` having produced a Vite manifest.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
