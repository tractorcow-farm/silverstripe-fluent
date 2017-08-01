<?php

namespace TractorCow\Fluent\Extension;

use GoogleSitemapController;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use TractorCow\Fluent\Control\SitemapController;
use TractorCow\Fluent\Model\Locale;

/**
 * Fluent extension for {@link \SilverStripe\Control\Director} to apply routing rules for locales
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
     * Request parameter to store the locale in
     *
     * @config
     * @var string
     */
    private static $query_param = 'l';

    /**
     * Allow the prefix for the default {@link Locale} (IsDefault = 1) to be disabled
     *
     * @config
     * @var bool
     */
    private static $disable_default_prefix = false;

    /**
     * Forces regeneration of all locale routes
     *
     * @param array &$rules
     */
    public function updateRules(&$rules)
    {
        $originalRules = $rules;
        $rules = $this->getExplicitRoutes();

        // If Google sitemap module is installed then replace default controller with custom controller
        if (class_exists(GoogleSitemapController::class)) {
            $rules['sitemap.xml'] = SitemapController::class;
        }

        // Merge all other routes (maintain priority)
        foreach ($originalRules as $key => $route) {
            if (!isset($rules[$key])) {
                $rules[$key] = $route;
            }
        }

        // Home page route
        $rules[''] = [
            'Controller' => RootURLController::class
        ];

        $defaultLocale = Locale::get()->filter('IsDefault', 1)->first();
        if (!$defaultLocale) {
            return;
        }

        // If we do not wish to detect the locale automatically, fix the home page route
        // to the default locale for this domain.
        if (!static::config()->get('detect_locale')) {
            $rules[''][static::config()->get('query_param')] = $defaultLocale->Locale;
        }

        // If default locale doesn't have prefix, replace default route with
        // the default locale for this domain
        if (static::config()->get('disable_default_prefix')) {
            $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                'Controller' => ModelAsController::class,
                static::config()->get('query_param') => $defaultLocale->Locale
            ];
        }
    }

    /**
     * Generate an array of explicit routing rules for each locale
     *
     * @return array
     */
    protected function getExplicitRoutes()
    {
        $queryParam = static::config()->get('query_param');
        $rules = [];
        foreach (Locale::get() as $localeObj) {
            $locale = $localeObj->Locale;
            $url = $localeObj->URLSegment ?: $locale;

            $rules[$url . '/$URLSegment!//$Action/$ID/$OtherID'] = [
                'Controller' => ModelAsController::class,
                $queryParam => $locale
            ];
            $rules[$url] = [
                'Controller' => RootURLController::class,
                $queryParam => $locale
            ];
        }
        return $rules;
    }
}
