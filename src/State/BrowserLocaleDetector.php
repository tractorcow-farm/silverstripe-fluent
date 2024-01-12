<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use TractorCow\Fluent\Model\Locale;

/**
 * Detects locale based on browser locale
 */
class BrowserLocaleDetector implements LocaleDetector
{
    use Extensible;
    use Injectable;

    /**
     * Determines the locale best matching the given list of browser locales
     *
     * @param HTTPRequest $request
     * @return Locale The matching locale, or null if none could be determined
     */
    public function detectLocale(HTTPRequest $request)
    {
        // Given multiple canditates, narrow down the final result using the client's preferred languages
        $inputLocales = $request->getHeader('Accept-Language');
        if (empty($inputLocales)) {
            return null;
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
                $priority = empty($priority) ? "1.0" : (string)floatval($priority);
                if (empty($prioritisedLocales[$priority])) {
                    $prioritisedLocales[$priority] = [];
                }
                $prioritisedLocales[$priority][] = $locale;
            }

            // sort list based on value
            krsort($prioritisedLocales, SORT_NUMERIC);
        }

        // Check each requested locale against loaded locales
        $locales = Locale::getLocales(true);
        foreach ($prioritisedLocales as $priority => $parsedLocales) {
            foreach ($parsedLocales as $browserLocale) {
                foreach ($locales as $localeObj) {
                    if ($localeObj->isLocale($browserLocale)) {
                        return $localeObj;
                    }
                }
            }
        }
        return null;
    }
}
