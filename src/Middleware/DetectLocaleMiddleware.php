<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\State\LocaleDetector;

/**
 * DetectLocaleMiddleware will detect if a locale has been requested (or is default) and is not the current
 * locale, and will redirect the user to that locale if needed.
 */
class DetectLocaleMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * IDs to persist the locale in cookies / session in the front end, CMS, etc
     *
     * @config
     * @var string[]
     */
     #
    private static $persist_ids = [
        'frontend' => 'FluentLocale',
        'cms' => 'FluentLocale_CMS',
    ];

    /**
     * The expiry time in days for a locale persistence cookie
     *
     * @config
     * @var int
     */
    private static $persist_cookie_expiry = 90;

    /**
     * Use this path when setting the locale cookie
     *
     * @config
     * @var string
     */
    private static $persist_cookie_path = null;

    /**
     * Use this domain when setting the locale cookie
     *
     * @config
     * @var string
     */
    private static $persist_cookie_domain = null;

    /**
     * Whether cookies have already been set during {@link setPersistLocale()}
     *
     * @var bool
     */
    protected $cookiesPersisted = false;

    /**
     * Sets the current locale to the FluentState, provided no previous middleware has set it first
     *
     * {@inheritDoc}
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        $state = FluentState::singleton();
        $locale = $state->getLocale();

        if (!$locale) {
            $locale = $this->getLocale($request);
            $state->setLocale($locale);
        }

        if ($locale && $state->getIsFrontend()) {
            i18n::set_locale($state->getLocale());
        }

        // Always persist the current locale
        $this->setPersistLocale($request, $state->getLocale());

        return $delegate($request);
    }

    /**
     * Get the current locale from routing parameters, persistence, browser locale, etc
     *
     * @param  HTTPRequest $request
     * @return string
     */
    protected function getLocale(HTTPRequest $request)
    {
        // Check direct request from either routing params, or request (e.g. GET) vars, in that order
        $locale = $this->getParamLocale($request);

        // Check against domain (non-ambiguous locale only)
        if (empty($locale)) {
            $locale = $this->getDomainLocale();
        }

        // Look for persisted locale
        if (empty($locale)) {
            $locale = $this->getPersistLocale($request);
        }

        // Use locale detector (if configured)
        if (empty($locale)) {
            $locale = $this->getDetectedLocale($request);
        }

        // Fallback to default if empty or invalid
        if (empty($locale) || !Locale::getByLocale($locale)) {
            $locale = $this->getDefaultLocale();
        }

        return (string) $locale;
    }

    /**
     * Gets the locale currently set within either the session or cookie.
     *
     * @param HTTPRequest $request
     * @return null|string The locale, if available
     */
    protected function getPersistLocale(HTTPRequest $request)
    {
        $key = $this->getPersistKey();

        // Skip persist if key is unset
        if (empty($key)) {
            return null;
        }

        // check session then cookies
        if ($locale = $request->getSession()->get($key)) {
            return $locale;
        }

        if ($locale = Cookie::get($key)) {
            return $locale;
        }

        return null;
    }

    /**
     * Specify the locale to persist between sessions, or to use for the locale outside of locale-routed pages
     * (such as in unit tests, custom controllers, etc).
     *
     * Not to be confused with the temporary locale assigned with {@link withLocale()}. @todo implement this.
     *
     * @param  HTTPRequest $request
     * @param  string      $locale  Locale to assign
     * @return $this
     */
    protected function setPersistLocale(HTTPRequest $request, $locale)
    {
        $key = $this->getPersistKey();

        // Skip persist if key is unset
        if (empty($key)) {
            return $this;
        }

        // Save locale
        if ($locale) {
            $request->getSession()->set($key, $locale);
        } else {
            $request->getSession()->clear($key);
        }

        // Don't set cookie if headers already sent
        if (!headers_sent()) {
            Cookie::set(
                $key,
                $locale,
                static::config()->get('persist_cookie_expiry'),
                static::config()->get('persist_cookie_path'),
                static::config()->get('persist_cookie_domain'),
                false,
                false
            );
        }

        return $this;
    }

    /**
     * Get the Fluent locale persistence key. See the "persist_ids" config static.
     *
     * @return string
     */
    protected function getPersistKey()
    {
        $persistIds = static::config()->get('persist_ids');
        return FluentState::singleton()->getIsFrontend()
            ? $persistIds['frontend']
            : $persistIds['cms'];
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
        $locale = (string)($request->param($queryParam) ?: $request->requestVar($queryParam));
        return $locale;
    }

    /**
     * Get locale from the domain, if the current domain has exactly one locale
     *
     * @return string
     */
    protected function getDomainLocale()
    {
        $state = FluentState::singleton();

        // Ensure a domain is configured
        if (!$state->getIsDomainMode()) {
            return null;
        }
        $domain = $state->getDomain();
        if (!$domain) {
            return null;
        }

        // Get domain
        $domainObj = Domain::getByDomain($domain);
        if (!$domainObj) {
            return null;
        }

        // If the current domain has exactly one locale, the locale is non-ambiguous
        $locales = Locale::getCached()->filter('DomainID', $domainObj->ID);
        if ($locales->count() === 1) {
            return $locales->first();
        }

        return null;
    }

    /**
     * Use the configured LocaleDetector to guess the locale
     *
     * @param HTTPRequest $request
     * @return string
     */
    protected function getDetectedLocale(HTTPRequest $request)
    {
        // Only detect on home page (landing page)
        if ($request->getURL() !== '') {
            return null;
        }
        // Respect config disable
        if (!FluentDirectorExtension::config()->get('detect_locale')) {
            return null;
        }

        /** @var LocaleDetector $detector */
        $detector = Injector::inst()->get(LocaleDetector::class);
        $localeObj = $detector->detectLocale($request);
        if ($localeObj) {
            return $localeObj->getLocale();
        }
        return null;
    }

    /**
     * Get default locale
     *
     * @return string
     */
    protected function getDefaultLocale()
    {
        // Get default from current domain
        $localeObj = Locale::getDefault(true);
        if ($localeObj) {
            return $localeObj->Locale;
        }

        return null;
    }
}
