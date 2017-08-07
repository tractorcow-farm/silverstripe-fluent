<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;

/**
 * HandleRedirectMiddleware will detect if a locale has been requested (or is default) and is not the current
 * locale, and will redirect the user to that locale if needed.
 */
class HandleRedirectMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        // @todo
        return $delegate($request);
    }
}
