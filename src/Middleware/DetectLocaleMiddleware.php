<?php

namespace TractorCow\Fluent\Middleware;

use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
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
     * Whether cookies have already been set during {@link setPersistLocale()}
     *
     * @var bool
     */
    protected $cookiesPersisted = false;

    public function process(HTTPRequest $request, callable $delegate)
    {
        $localeDetector = LocaleDetector::singleton()->setRequest($request);

        $state = FluentState::singleton();
        $state->setLocale($this->getLocale($request));

        $locale = $state->getLocale();
        $this->setPersistLocale($request, $locale, $this->getPersistKey());

        return $delegate($request);
    }

    /**
     * Get the current locale from routing parameters, persistence, browser locale, etc
     *
     * @param  HTTPRequest $request
     * @return string
     */
    public function getLocale(HTTPRequest $request)
    {
        $state = FluentState::singleton();

        // Check direct request
        $queryParam = FluentDirectorExtension::config()->get('query_param');
        $locale = (string) $request->param($queryParam) ?: $request->requestVar($queryParam);

        // Persistant variables
        if (empty($locale)) {
            $locale = $this->getPersistLocale($request);
        }

        // Check browser headers
        if (empty($locale)) {
            $locale = LocaleDetector::singleton()->detectBrowserLocale();
        }

        // Fallback to default if empty or invalid (for this domain)
        if (empty($locale) || !Locale::getByLocale($locale)) {
            // If on the frontend, filter locales by the current domain
            $domain = $state->getIsFrontend($request) ? $state->getDomain() : null;

            /** @var Locale $locale */
            if ($locale = Locale::getDefault($domain)) {
                $locale = $locale->Locale;
            }
        }

        return (string) $locale;
    }

    /**
     * Gets the locale currently set within either the session or cookie.
     *
     * @param  HTTPRequest $request
     * @param  string      $key     ID to retrieve persistant locale from. Will automatically detect if omitted. See
     *                              "persist_ids" config static.
     * @return string|null          The locale, if available
     */
    public function getPersistLocale(HTTPRequest $request, $key = null)
    {
        $key = $this->getPersistKey($key);

        // Skip persist if key is unset
        if (empty($key)) {
            return;
        }

        // check session then cookies
        if ($locale = $request->getSession()->get($key)) {
            return $locale;
        }

        if ($locale = Cookie::get($key)) {
            return $locale;
        }
    }

    /**
     * Specify the locale to persist between sessions, or to use for the locale outside of locale-routed pages
     * (such as in unit tests, custom controllers, etc).
     *
     * Not to be confused with the temporary locale assigned with {@link withLocale()}. @todo implement this.
     *
     * @param  HTTPRequest $request
     * @param  string      $locale  Locale to assign
     * @param  string      $key     ID to set the locale against. Will automatically detect if omitted. See
     *                              "persist_ids" config static.
     * @return $this
     */
    public function setPersistLocale(HTTPRequest $request, $locale, $key = null)
    {
        $key = $this->getPersistKey($key);

        // Skip persist if key is unset
        if (empty($key)) {
            return;
        }

        // Save locale
        if ($locale) {
            $request->getSession()->set($key, $locale);
        } else {
            $request->getSession()->clear($key);
        }

        // Prevent unnecessarily excessive cookie assigment
        if (!headers_sent() && (!isset($this->lastSetLocale[$key]) || $this->lastSetLocale[$key] !== $locale)) {
            $this->lastSetLocale[$key] = $locale;
            Cookie::set($key, $locale, static::config()->get('persist_cookie_expiry'), null, null, false, false);
        }

        return $this;
    }

    /**
     * Get the Fluent locale persistence key. See the "persist_ids" config static.
     *
     * @param  string|null $key
     * @return string
     */
    public function getPersistKey($key = null)
    {
        if (empty($key)) {
            $persistIds = static::config()->get('persist_ids');
            $key = FluentState::singleton()->getIsFrontend() ? $persistIds['frontend'] : $persistIds['cms'];
        }
        return $key;
    }
}
