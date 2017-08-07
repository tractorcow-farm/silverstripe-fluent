<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Middleware\DetectLocaleMiddleware;
use TractorCow\Fluent\State\FluentState;

class DetectLocaleMiddlewareTest extends SapphireTest
{
    public function testGetPersistKey()
    {
        $middleware = new DetectLocaleMiddleware;
        $this->assertSame('foo', $middleware->getPersistKey('foo'));

        $state = FluentState::singleton();
        $state->setIsFrontend(true);
        $this->assertSame('FluentLocale', $middleware->getPersistKey());

        $state->setIsFrontend(false);
        $this->assertSame('FluentLocale_CMS', $middleware->getPersistKey());
    }
}
