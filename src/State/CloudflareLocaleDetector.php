<?php

namespace TractorCow\Fluent\State;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use TractorCow\Fluent\Model\Locale;

/**
 * Detects locale based on cloudflare geo-location headers
 *
 * @link https://support.cloudflare.com/hc/en-us/articles/200168236-Configuring-Cloudflare-IP-Geolocation
 */
class CloudflareLocaleDetector implements LocaleDetector
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
        // Get IP Country
        $ipCountry = $request->getHeader('CF-IPCountry');
        if (empty($ipCountry)) {
            return null;
        }

        // Skip Tor / unknown
        if (in_array(strtolower($ipCountry), ['xx', 't1'])) {
            return null;
        }

        // Check each requested locale against loaded locales
        $locales = Locale::getLocales(true);
        foreach ($locales as $localeObj) {
            list ($lang, $country) = explode('_', $localeObj->Locale);
            if (strcasecmp($country, $ipCountry) === 0) {
                return $localeObj;
            }
        }

        return null;
    }
}
