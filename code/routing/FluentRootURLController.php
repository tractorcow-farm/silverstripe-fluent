<?php

/**
 * Home page controller for multiple locales
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentRootURLController extends RootURLController
{
    /**
     * Determine if the referrer for this request is from a domain within this website's scope
     *
     * @return boolean
     */
    protected function knownReferrer()
    {

        // Extract referrer
        if (empty($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        $hostname = strtolower(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST));

        // Check if internal traffic
        if ($hostname == strtolower($_SERVER['HTTP_HOST'])) {
            return true;
        }

        // Check configured domains
        $domains = Fluent::domains();
        return isset($domains[$hostname]);
    }

    /**
     * For incoming traffic to the site root, determine if they should be redirected to any locale.
     *
     * @return string|null The locale to redirect to, or null
     */
    protected function getRedirectLocale()
    {

        // Redirection interfere with flushing, so don't redirect
        if (isset($_GET['flush'])) {
            return null;
        }

        // Don't redirect if the user has clicked a link on the locale menu
        if ($this->knownReferrer()) {
            return null;
        }

        // Redirect if this user has previously viewed a page in any locale
        if (Fluent::config()->remember_locale && ($locale = Fluent::get_persist_locale())) {
            return $locale;
        }

        // Detect locale from browser Accept-Language header
        if (Fluent::config()->detect_locale && ($locale = Fluent::detect_browser_locale())) {
            return $locale;
        }
    }

    public function handleRequest(SS_HTTPRequest $request, DataModel $model = null)
    {
        self::$is_at_root = true;
        $this->setDataModel($model);

        $this->pushCurrent();
        $this->init();
        $this->setRequest($request);

        // Check for existing routing parameters, redirecting to another locale automatically if necessary
        $locale = Fluent::get_request_locale();
        if (empty($locale)) {

            // Determine if this user should be redirected
            $locale = $this->getRedirectLocale();
            $this->extend('updateRedirectLocale', $locale);

            // Check if the user should be redirected
            $domainDefault = Fluent::default_locale(true);
            if (Fluent::is_locale($locale) && ($locale !== $domainDefault)) {
                // Check new traffic with detected locale
                return $this->redirect(Fluent::locale_baseurl($locale));
            }

            // Reset parameters to act in the default locale
            $locale = $domainDefault;
            Fluent::set_persist_locale($locale);
            $params = $request->routeParams();
            $params[Fluent::config()->query_param] = $locale;
            $request->setRouteParams($params);
        }

        if (!DB::isActive() || !ClassInfo::hasTable('SiteTree')) {
            $this->response = new SS_HTTPResponse();
            $this->response->redirect(Director::absoluteBaseURL() . 'dev/build?returnURL=' . (isset($_GET['url']) ? urlencode($_GET['url']) : null));
            return $this->response;
        }

        $localeURL = Fluent::alias($locale);
        $request->setUrl(self::fluent_homepage_link($localeURL));
        $request->match($localeURL . '/$URLSegment//$Action', true);

        $controller = new ModelAsController();
        $result = $controller->handleRequest($request, $model);

        $this->popCurrent();
        return $result;
    }

    /**
     * With Translatable installed, don't pre-append the locale to the homepage
     * URL.
     *
     * @param string $localeURL
     * @return string
     * @see {@link Translatable::get_homepage_link()}.
     */
    public static function fluent_homepage_link($localeURL)
    {
        $homepageLink = parent::get_homepage_link();

        /*
         * Don't prefix when Translatable is installed becuase of baked-in logic
         * contained in RootURLController::get_homepage_link(). This causes duplicate
         * locales to be returned.
         */
        if (class_exists('Translatable')) {
            return $homepageLink . '/';
        }

        return $localeURL . '/' . $homepageLink . '/';
    }
}
