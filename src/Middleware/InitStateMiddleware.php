<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\State\FluentState;

/**
 * InitStateMiddleware initialises the FluentState object and sets the current request locale and domain to it
 */
class InitStateMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * @var HTTPRequest
     */
    protected $request;

    /**
     * URL paths that should be considered as admin only, i.e. not frontend
     *
     * @config
     * @var array
     */
    private static $admin_url_paths = [
        'dev/',
        'graphql/',
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        $this->setRequest($request);

        $state = FluentState::create();
        if ($locale = $this->getRequestLocale()) {
            $state->setLocale($locale);
        }

        if ($domain = $this->getRequestDomain()) {
            $state->setDomain($domain);
        }

        $state
            ->setIsFrontend($this->getIsFrontend())
            ->setIsDomainMode($this->getIsDomainMode());

        Injector::inst()->registerService($state, FluentState::class);

        return $delegate($request);
    }

    /**
     * @return HTTPRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param  HTTPRequest $request
     * @return $this
     */
    public function setRequest(HTTPRequest $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Check for existing locale routing parameters and return if available
     *
     * @return string
     */
    public function getRequestLocale()
    {
        $queryParam = FluentDirectorExtension::config()->get('query_param');
        return (string) $this->getRequest()->getVar($queryParam);
    }

    /**
     * Gets the current domain from the request
     *
     * @return string
     */
    public function getRequestDomain()
    {
        return strtolower((string) $this->getRequest()->getHeader('Host'));
    }

    /**
     * Determine whether the website is being viewed from the frontend or not
     *
     * @return bool
     */
    public function getIsFrontend()
    {
        $adminPaths = static::config()->get('admin_url_paths');
        $adminPaths[] = AdminRootController::config()->get('url_base') . '/';
        $currentPath = rtrim($this->getRequest()->getURL(), '/') . '/';

        foreach ($adminPaths as $adminPath) {
            if (substr($currentPath, 0, strlen($adminPath)) === $adminPath) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine whether the website is running in domain segmentation mode
     *
     * @return boolean
     */
    public function getIsDomainMode()
    {
        // Don't act in domain mode if none exist
        if (!Domain::getCached()->exists()) {
            return false;
        }

        // Check environment for a forced override
        if (getenv('SS_FLUENT_FORCE_DOMAIN')) {
            return true;
        }

        // Check config for a forced override
        if (FluentDirectorExtension::config()->get('force_domain')) {
            return true;
        }

        // Check if the current domain is included in the list of configured domains (default)
        return Domain::getCached()->filter('Domain', $this->getRequestDomain())->exists();
    }
}
