<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_is_treated_as_secure(): void
    {
        $this->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://sima.ahpeduli.org/up');

        $this->assertTrue($this->app['request']->isSecure());
    }
}
