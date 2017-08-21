<?php

namespace TractorCow\Fluent\Tests\Middleware\Stub;

use TractorCow\Fluent\Middleware\DetectLocaleMiddleware;
use SilverStripe\Dev\TestOnly;

/**
 * Opens up DetectLocaleMiddleware protected methods for unit testing
 */
class DetectLocaleMiddlewareSpy extends DetectLocaleMiddleware implements TestOnly
{
    public function __call($method, $arguments)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$arguments);
        }
    }
}
