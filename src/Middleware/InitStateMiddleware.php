<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
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
     * URL paths that should be considered as admin only, i.e. not frontend
     *
     * @config
     * @var array
     */
    private static $admin_url_paths = [
        'dev/',
        'admin/',
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        return FluentState::singleton()
            ->withState(function ($state) use ($delegate, $request) {
                // Detect frontend
                $isFrontend = $this->getIsFrontend($request);

                // Only set domain mode on the frontend
                $isDomainMode = $isFrontend ? $this->getIsDomainMode($request) : false;

                // Don't set domain unless in domain mode
                $domain = $isDomainMode ? Director::host($request) : null;
                // Update state
                $state
                    ->setIsFrontend($isFrontend)
                    ->setIsDomainMode($isDomainMode)
                    ->setDomain($domain);

                return $delegate($request);
            });
    }

    /**
     * Determine whether the website is being viewed from the frontend or not
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public function getIsFrontend(HTTPRequest $request)
    {
        $adminPaths = static::config()->get('admin_url_paths');
        $adminPaths[] = AdminRootController::config()->get('url_base') . '/';
        $currentPath = rtrim($request->getURL(), '/') . '/';

        foreach ($adminPaths as $adminPath) {
            if (substr($currentPath, 0, strlen($adminPath)) === $adminPath) {
                return false;
            }
        }

        // If using the CMS preview, do not treat the site as frontend
        if ($request->getVar('CMSPreview')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the website is running in domain segmentation mode
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public function getIsDomainMode(HTTPRequest $request)
    {
        // Don't act in domain mode if none exist
        if (!Domain::getCached()->exists()) {
            return false;
        }

        // Check environment for a forced override
        if (Environment::getEnv('SS_FLUENT_FORCE_DOMAIN')) {
            return true;
        }

        // Check config for a forced override
        if (FluentDirectorExtension::config()->get('force_domain')) {
            return true;
        }

        // Check if the current domain is included in the list of configured domains (default)
        return Domain::getCached()->filter('Domain', Director::host($request))->exists();
    }
}
