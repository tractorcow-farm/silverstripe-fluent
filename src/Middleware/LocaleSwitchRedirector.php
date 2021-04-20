<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\State\FluentState;

/**
 * This middleware will safely redirect you to the cms landing page when switching locales,
 * in case you were viewing a url that does not apply to the new locale.
 */
class LocaleSwitchRedirector implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        // Get and capture any possible errors
        try {
            $response = $delegate($request);
        } catch (HTTPResponse_Exception $ex) {
            $response = $ex->getResponse();
        }

        // Check if this is a 404 error attempting when switching locales in the CMS
        $state = FluentState::singleton();
        if ($response->getStatusCode() === 404
            && !$state->getIsFrontend()
            && $this->getParamLocale($request)
        ) {
            // Redirect to the CMS home page if the requested page doesn't exist
            $response = new HTTPResponse();
            $response->redirect(Controller::join_links(
                Director::baseURL(),
                AdminRootController::admin_url()
            ));
        }

        return $response;
    }

    /**
     * Get locale from the query_param
     *
     * @param HTTPRequest $request
     * @return mixed
     */
    protected function getParamLocale(HTTPRequest $request)
    {
        $queryParam = FluentDirectorExtension::config()->get('query_param');
        return (string)($request->param($queryParam) ?: $request->requestVar($queryParam));
    }
}
