<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\State\FluentState;

/**
 * FluentMiddleware is responsible for redirection to locale, initialising the State object, etc
 */
class FluentMiddleware implements HTTPMiddleware
{
    /**
     * Initialise a new Fluent State object and handle locale redirections
     *
     * {@inheritDoc}
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        $this->registerState($request);

        if ($redirect = $this->handleRedirects($request)) {
            return $redirect;
        }

        return $delegate($request);
    }

    /**
     * Create and register a new {@link FluentState} instance
     *
     * @param  HTTPRequest $request
     * @return $this
     */
    public function registerState(HTTPRequest $request)
    {
        $state = new FluentState;
        $state->setRequest($request);
        if ($locale = $this->getRequestLocale($request)) {
            $state->setLocale($locale);
        }

        Injector::inst()->registerService($state, State::class);

        return $this;
    }

    /**
     * @todo
     * @param  HTTPRequest $request
     * @return HTTPResponse|null
     */
    public function handleRedirects(HTTPRequest $request)
    {
        return null;
    }

    /**
     * Get the current locale code from the request
     *
     * @param  HTTPRequest $request
     * @return string
     */
    public function getRequestLocale(HTTPRequest $request)
    {
        $queryParam = FluentDirectorExtension::config()->get('query_param');

        return (string) $request->getVar($queryParam);
    }
}
