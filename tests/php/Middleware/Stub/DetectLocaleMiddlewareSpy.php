<?php

namespace TractorCow\Fluent\Tests\Middleware\Stub;

use SilverStripe\Control\HTTPRequest;
use TractorCow\Fluent\Middleware\DetectLocaleMiddleware;
use SilverStripe\Dev\TestOnly;

/**
 * Opens up DetectLocaleMiddleware protected methods for unit testing
 *
 * @method string getPersistKey()
 * @method $this setPersistLocale(HTTPRequest $request, $locale)
 * @method string getLocale(HTTPRequest $request)
 */
class DetectLocaleMiddlewareSpy extends DetectLocaleMiddleware implements TestOnly
{
    public function __call($method, $arguments)
    {
        if (method_exists($this, $method ?? '')) {
            return $this->$method(...$arguments);
        }
    }
}
