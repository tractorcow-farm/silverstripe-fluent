<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\FluentMemberExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\State\LocaleDetector;

/**
 * DetectLocaleMiddleware will detect if a locale has been requested (or is default) and is not the current
 * locale, and will redirect the user to that locale if needed.
 * Will cascade through different checks in order, see "configuration" docs for details.
 * Additionally, detected locales will be set in session and cookies.
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
    private static $persist_ids = [
        'frontend' => 'FluentLocale',
        'cms'      => 'FluentLocale_CMS',
    ];

    /**
     * Use cookies for locale persistence.
     * Caution: This can make it hard to activate HTTP caching,
     * since many HTTP proxies (e.g. CDNs) won't cache with cookies.
     *
     * @config
     * @var bool
     */
    private static $persist_cookie = true;

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
     * Use http-only cookies. Set to false if you need js access.
     *
     * @config
     * @var bool
     */
    private static $persist_cookie_http_only = true;

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
        return FluentState::singleton()
            ->withState(function ($state) use ($delegate, $request) {
                $locale = $state->getLocale();

                if (!$locale) {
                    $locale = $this->getLocale($request);
                    $state->setLocale($locale);
                }

                // Validate the user is allowed to access this locale
                $this->validateAllowedLocale($state);

                if ($locale && $state->getIsFrontend()) {
                    i18n::set_locale($state->getLocale());
                }

                // Persist the current locale if it has a value.
                // Distinguishes null from empty strings in order to unset locales.
                $newLocale = $state->getLocale();
                if (!is_null($newLocale)) {
                    $this->setPersistLocale($request, $newLocale);
                }

                return $delegate($request);
            });
    }

    /**
     * Get the current locale from routing parameters, persistence, browser locale, etc
     *
     * @param HTTPRequest $request
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

        return (string)$locale;
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
        $session = $request->getSession();
        if ($session->isStarted() && ($locale = $session->get($key))) {
            return $locale;
        }

        if (static::config()->get('persist_cookie') && ($locale = Cookie::get($key))) {
            return $locale;
        }

        return null;
    }

    /**
     * Specify the locale to persist between sessions, or to use for the locale outside of locale-routed pages
     * (such as in unit tests, custom controllers, etc).
     *
     * Not to be confused with the temporary locale assigned with {@link withLocale()}. @param HTTPRequest $request
     * @param string $locale Locale to assign
     * @return $this
     * @todo implement this.
     *
     */
    protected function setPersistLocale(HTTPRequest $request, $locale)
    {
        $key = $this->getPersistKey();

        // Skip persist if key is unset
        if (empty($key)) {
            return $this;
        }

        // Save locale
        $session = $request->getSession();
        if ($session->isStarted() && $session->getAll()) {
            if ($locale) {
                $session->set($key, $locale);
            } else {
                $session->clear($key);
            }
        }

        // Don't set cookie if headers already sent
        if (static::config()->get('persist_cookie') && !headers_sent()) {
            // Use secure cookies if session does
            $secure = Director::is_https($request)
                && Session::config()->get('cookie_secure');
            Cookie::set(
                $key,
                $locale,
                static::config()->get('persist_cookie_expiry'),
                static::config()->get('persist_cookie_path'),
                static::config()->get('persist_cookie_domain'),
                $secure,
                static::config()->get('persist_cookie_http_only')
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
        if ($locales->count() == 1) {
            $localeObject = $locales->first();
            if ($localeObject) {
                return $localeObject->getLocale();
            }
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

    /**
     * Check if the current user is allowed to access the curernt locale
     *
     * @param FluentState $state
     */
    protected function validateAllowedLocale(FluentState $state)
    {
        if ($state->getIsFrontend()) {
            return;
        }

        /** @var Member|FluentMemberExtension $member */
        $member = Security::getCurrentUser();
        if (!$member) {
            return;
        }

        // If limited to one or more locales, check that the current locale is in
        // this list
        $allowedLocales = $member->getCMSAccessLocales();
        $firstAllowedLocale = $allowedLocales->first();
        if ($firstAllowedLocale && !$allowedLocales->find('Locale', $state->getLocale())) {
            // Force state to the first allowed locale
            $state->setLocale($firstAllowedLocale->Locale);
        }
    }
}
