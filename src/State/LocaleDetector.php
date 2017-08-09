<?php

namespace TractorCow\Fluent\State;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Model\Domain;
use TractorCow\Fluent\Model\Locale;

class LocaleDetector
{
    use Extensible;
    use Injectable;

    public function __construct()
    {
        $this->constructExtensions();
    }

    /**
     * For incoming traffic to the site root, determine if they should be redirected to any locale.
     *
     * @param  HTTPRequest $request
     * @return string|null The locale to redirect to, or null
     */
    public function getRedirectLocale(HTTPRequest $request)
    {
        // Redirection interfere with flushing, so don't redirect
        if (array_key_exists('flush', $request->getVars())) {
            $locale = null;
        }

        // Don't redirect if the user has clicked a link on the locale menu
        if (!isset($locale) && $this->getIsKnownReferrer($request)) {
            $locale = null;
        }

        // Redirect if this user has previously viewed a page in any locale
        if (!isset($locale)
            && FluentDirectorExtension::config()->get('remember_locale')
            && ($persistLocale = $this->getState()->getPersistLocale())
        ) {
            $locale = $persistLocale;
        }

        // Detect locale from browser Accept-Language header
        if (!isset($locale)
            && FluentDirectorExtension::config()->get('detect_locale')
            && ($browserLocale = $this->detectBrowserLocale($request))
        ) {
            $locale = $browserLocale;
        }

        $this->extend('updateRedirectLocale', $locale);
    }

    /**
     * Determines the locale best matching the given list of browser locales
     *
     * @param  HTTPRequest $request
     * @param  bool $currentDomain Domain to determine the locales for. If false, the global list be returned.
     *                             If true, then the current domain will be used.
     * @return string              The matching locale, or null if none could be determined
     */
    public function detectBrowserLocale(HTTPRequest $request, $currentDomain = false)
    {
        // Given multiple canditates, narrow down the final result using the client's preferred languages
        $inputLocales = $request->getHeader('Accept-Language');
        if (empty($inputLocales)) {
            return;
        }

        // Generate mapping of priority => list of locales at this priority
        // break up string into pieces (languages and q factors)
        preg_match_all(
            '/(?<code>[a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(?<priority>1|0\.[0-9]+))?/i',
            $inputLocales,
            $parsedLocales
        );

        $prioritisedLocales = [];
        if (count($parsedLocales['code'])) {
            // create a list like "en" => 0.8
            $parsedLocales = array_combine($parsedLocales['code'], $parsedLocales['priority']);

            // Generate nested list of priorities => [locales]
            foreach ($parsedLocales as $locale => $priority) {
                $priority = empty($priority) ? 1.0 : floatval($priority);
                if (empty($prioritisedLocales[$priority])) {
                    $prioritisedLocales[$priority] = [];
                }
                $prioritisedLocales[$priority][] = $locale;
            }

            // sort list based on value
            krsort($prioritisedLocales, SORT_NUMERIC);
        }

        // Check each requested locale against loaded locales
        foreach ($prioritisedLocales as $priority => $parsedLocales) {
            foreach ($parsedLocales as $browserLocale) {
                foreach ($this->getLocales($currentDomain) as $localeObj) {
                    /** @var Locale $localeObj */
                    if ($localeObj->isLocale($browserLocale)) {
                        return $localeObj->Locale;
                    }
                }
            }
        }
    }

    /**
     * Determine if the referrer for this request is from a domain within this website's scope
     *
     * @param  HTTPRequest $request
     * @return boolean
     */
    public function getIsKnownReferrer(HTTPRequest $request)
    {
        // Extract referrer
        if (!$request->getHeader('Referer')) {
            return false;
        }

        $hostname = strtolower(parse_url($request->getHeader('Referer'), PHP_URL_HOST));
        // Check if internal traffic
        if ($hostname == strtolower($request->getHeader('Host'))) {
            return true;
        }

        // Check configured domains
        $domain = Domain::getCached()->filter('Domain', $hostname)->first();
        return $domain && $domain->exists();
    }

    /**
     * Get a list of available locales, optionally for the given domain
     *
     * @param  bool $currentDomain Domain to determine the locales for. If false, the global list be returned.
     *                             If true, then the current domain will be used.
     * @return \SilverStripe\ORM\SS_List
     */
    public function getLocales($currentDomain = false)
    {
        if ($currentDomain) {
            $domain = Domain::getCached()->filter('Domain', $this->getState()->getDomain())->first();
            if (!$domain) {
                return ArrayList::create();
            }

            return $domain->Locales();
        }

        return Locale::getCached();
    }

    /**
     * Decide whether the given locale is the current locale
     *
     * @param  string $locale
     * @return boolean
     */
    public function getIsLocale($locale)
    {
        return strtolower($locale) === strtolower($this->getState()->getLocale());
    }

    /**
     * Determine the baseurl within a specified $locale
     *
     * @param  string $locale Locale, or null to use current locale
     * @return string
     */
    public function getBaseUrl($locale = null)
    {
        if (empty($locale)) {
            $locale = $this->getState()->getLocale();
        }
        /** @var Locale $localeObj */
        $localeObj = $locale ? Locale::getByLocale($locale) : Locale::getDefault();

        // Build domain-specific base url
        $base = Director::baseURL();
        if ($domain = $this->getDomainForLocale($locale)) {
            $base = Controller::join_links(Director::protocol() . $domain, $base);
        }

        // Don't append locale to home page for default locale
        if ($locale === Locale::getDefault($domain)) {
            return $base;
        }

        // Append locale otherwise
        return Controller::join_links($base, $localeObj->URLSegment ?: $locale, '/');
    }

    /**
     * Determine the home domain for this locale
     *
     * @param string $locale
     * @param string|null $domain
     */
    public function getDomainForLocale($locale)
    {
        $locale = Locale::getByLocale($locale);
        if ($locale && ($domain = $locale->Domain())) {
            return $domain->Domain;
        }
    }

    /**
     * Get the current Fluent state object
     *
     * @return FluentState
     */
    public function getState()
    {
        return FluentState::singleton();
    }
}
