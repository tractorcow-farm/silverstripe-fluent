<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
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
     * Allow the prefix for the default {@link Locale} (IsDefault = 1) to be disabled
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
     * An option to override root controller class
     * useful for custom routing
     *
     * @config
     * @var string
     */
    private static $root_controller_class = RootURLController::class;

    /**
     * An option to override model controller class
     * useful for custom routing
     *
     * @config
     * @var string
     */
    private static $model_controller_class = ModelAsController::class;

    /**
     * Forces regeneration of all locale routes
     *
     * @param array &$rules
     */
    public function updateRules(&$rules)
    {
        $originalRules = $rules;
        $rules = $this->getExplicitRoutes();

        // @todo - Build SitemapController after GoogleSitemap module is migrated to ss4
        // If Google sitemap module is installed then replace default controller with custom controller
        //if (class_exists(GoogleSitemapController::class)) {
        //    $rules['sitemap.xml'] = SitemapController::class;
        //}

        // Merge all other routes (maintain priority)
        foreach ($originalRules as $key => $route) {
            if (!isset($rules[$key])) {
                $rules[$key] = $route;
            }
        }

        $defaultLocale = Locale::getDefault(true);
        if (!$defaultLocale) {
            return;
        }

        // If we do not wish to detect the locale automatically, fix the home page route
        // to the default locale for this domain.
        if (!static::config()->get('detect_locale')) {
            $rules[''] = [
                'Controller' => static::config()->get('root_controller_class'),
                static::config()->get('query_param') => $defaultLocale->Locale,
            ];
        }

        // If default locale doesn't have prefix, replace default route with
        // the default locale for this domain
        if (static::config()->get('disable_default_prefix')) {
            $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                'Controller' => static::config()->get('model_controller_class'),
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
        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $localeObj) {
            $locale = $localeObj->getLocale();
            $url = $localeObj->getURLSegment();

            $rules[$url . '/$URLSegment!//$Action/$ID/$OtherID'] = [
                'Controller' => static::config()->get('model_controller_class'),
                $queryParam => $locale,
            ];
            $rules[$url] = [
                'Controller' => static::config()->get('root_controller_class'),
                $queryParam => $locale,
            ];
        }
        return $rules;
    }
}
