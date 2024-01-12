<?php

namespace TractorCow\Fluent\Extension;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\Middleware\InitStateMiddleware;
use TractorCow\Fluent\Model\Locale;

/**
 * Fluent extension for {@link \SilverStripe\Control\Director} to apply routing rules for locales
 *
 * @extends Extension<Director>
 */
class FluentDirectorExtension extends Extension
{
    use Configurable;

    /**
     * Determine if the site should detect the browser locale for new users. Turn this off to disable 302 redirects
     * on the home page.
     *
     * @config
     * @var bool
     */
    private static $detect_locale = false;

    /**
     * Determine if the locale should be remembered across multiple sessions via cookies. If this is left on then
     * visitors to the home page will be redirected to the locale they last viewed. This may interefere with some
     * applications and can be turned off to prevent unexpected redirects.
     *
     * @config
     * @var bool
     */
    private static $remember_locale = false;

    /**
     * Request parameter to store the locale in
     *
     * @config
     * @var string
     */
    private static $query_param = 'l';

    /**
     * Allow the prefix for the default {@link Locale} (IsDefault = 1) to be disabled.
     *
     * If this is true, the `/' url will be the default locale. Note: You can also set this locale to use x-default
     * via the CMS admin.
     *
     * If this is false, the `/` url will be 'x-default', and the default locale home page will be /urlsegment/.
     *
     * @config
     * @var bool
     */
    private static $disable_default_prefix = false;

    /**
     * Whether to force "domain mode"
     *
     * @config
     * @var bool
     */
    private static $force_domain = false;

    /**
     * Forces regeneration of all locale routes
     *
     * @param array &$rules
     * @throws Exception
     */
    public function updateRules(&$rules)
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        if (!$request) {
            throw new Exception('No request found');
        }

        // Ensure InitStateMddleware is called here to set the correct defaultLocale
        Injector::inst()->create(InitStateMiddleware::class)
            ->process($request, function () use (&$rules) {
                $originalRules = $rules;
                $fluentRules = $this->getExplicitRoutes($rules);

                // Insert Fluent Rules before the default '$URLSegment//$Action/$ID/$OtherID'
                $rules = $this->insertRuleBefore($rules, '$URLSegment//$Action/$ID/$OtherID', $fluentRules);

                $defaultLocale = Locale::getDefault(true);
                $this->owner->extend('updateFluentDirectorDefaultLocale', $defaultLocale);
                if (!$defaultLocale) {
                    return;
                }

                // If we don't want to detect home page locale, or the home page only has one candidate locale anyway,
                if (!static::config()->get('detect_locale')
                    || $defaultLocale->getIsOnlyLocale()
                    || FluentDirectorExtension::config()->get('disable_default_prefix')
                ) {
                    // Respect existing home controller
                    $originalHomeRole = $originalRules[''] ?? null;
                    if ($originalHomeRole) {
                        $rules[''] = [
                            'Controller'                         => $this->getRuleController($originalHomeRole, $defaultLocale),
                            static::config()->get('query_param') => $defaultLocale->Locale,
                        ];
                    }
                }

                // If default locale doesn't have prefix, replace default route with
                // the default locale for this domain
                if (static::config()->get('disable_default_prefix')) {
                    $originalURLSegmentRule = $originalRules['$URLSegment//$Action/$ID/$OtherID'] ?? null;
                    if ($originalURLSegmentRule) {
                        $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                            'Controller'                         => $this->getRuleController($originalURLSegmentRule, $defaultLocale),
                            static::config()->get('query_param') => $defaultLocale->Locale
                        ];
                    }
                }

                // Hook for appending / adjusting any additional rules
                $this->owner->extend('updateFluentRoutes', $rules);
            });
    }

    /**
     * Generate an array of explicit routing rules for each locale
     *
     * @param array $originalRules
     * @return array
     */
    protected function getExplicitRoutes($originalRules)
    {
        $queryParam = static::config()->get('query_param');
        $rules = [];
        foreach (Locale::getCached() as $localeObj) {
            $locale = $localeObj->getLocale();
            $url = $localeObj->getURLSegment();

            // apply encode so we could route urls that contain multi-byte charaters
            $url = $this->encodeRoutePrefix($url);

            // Apply to nested page url
            $originalURLSegmentRule = $originalRules['$URLSegment//$Action/$ID/$OtherID'] ?? null;
            if ($originalURLSegmentRule) {
                $controller = $this->getRuleController($originalURLSegmentRule, $localeObj);
                $rules[$url . '/$URLSegment!//$Action/$ID/$OtherID'] = [
                    'Controller' => $controller,
                    $queryParam  => $locale,
                ];
            }

            // Home url for that locale
            $originalHomeRole = $originalRules[''] ?? null;
            if ($originalHomeRole) {
                $controller = $this->getRuleController($originalHomeRole, $localeObj);
                $rules[$url] = [
                    'Controller' => $controller,
                    $queryParam  => $locale,
                ];
            }

            // Hook for adding additional explicit routes
            $this->owner->extend('updateFluentRoutesForLocale', $rules, $locale, $url);
        }
        return $rules;
    }

    /**
     * Get controller that fluent should inject
     * @param array|string $existingRule
     * @param Locale       $localeObj
     * @return string Class name of controller to use
     */
    protected function getRuleController($existingRule, $localeObj)
    {
        $controller = isset($existingRule['Controller']) ? $existingRule['Controller'] : $existingRule;
        // Decorate Director class to override controllers for a specific locale
        $this->owner->extend('updateLocalePageController', $controller, $localeObj);
        return $controller;
    }

    /**
     * Inserts the given rule(s) before another rule
     * @param array   $rules            Array of rules to insert before
     * @param string  $key              Rule to insert the new rules before
     * @param array   $rule             New Rules to insert
     * @param boolean $prependIfMissing Prepend the new rules if the insert before rule cannot be found
     * @return array Resulting array of rules
     */
    protected function insertRuleBefore(array $rules, $key, array $rule, $prependIfMissing = true)
    {
        $i = array_search($key, array_keys($rules));
        if ($i !== false) {
            return array_slice($rules, 0, $i, true) + $rule + array_slice($rules, $i, null, true);
        } elseif ($prependIfMissing) {
            $rules = $rule + $rules;
        }

        return $rules;
    }

    /**
     * Safely encode urls
     *
     * @param string $url
     * @return string URL encoded url
     */
    protected function encodeRoutePrefix($url)
    {
        return implode('/', array_map('urlencode', explode('/', $url)));
    }
}
