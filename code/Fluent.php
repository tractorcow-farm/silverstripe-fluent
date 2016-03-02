<?php

/**
 * Bootstrapping and configuration object for Fluet localisation
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class Fluent extends Object implements TemplateGlobalProvider
{
    /**
     * Forces regeneration of all locale routes
     */
    public static function regenerate_routes()
    {
        $routes = array();

        // Explicit routes
        foreach (self::locales() as $locale) {
            $url = self::alias($locale);
            $routes[$url.'/$URLSegment!//$Action/$ID/$OtherID'] = array(
                'Controller' => 'ModelAsController',
                self::config()->query_param => $locale
            );
            $routes[$url] = array(
                'Controller' => 'FluentRootURLController',
                self::config()->query_param => $locale
            );
        }

        // Default route
        $routes[''] = 'FluentRootURLController';

        // If Google sitemap module is installed then replace default controller with custom controller
        if (class_exists('GoogleSitemapController')) {
            $routes['sitemap.xml'] = 'FluentSitemapController';
        }

        $singleton = singleton(__CLASS__);
        $singleton->extend('updateRegenerateRoutes', $routes);

        // Load into core routes
        Config::inst()->update('Director', 'rules', $routes);

        $singleton->extend('onAfterRegenerateRoutes');
    }

    /**
     * Initialise routes
     */
    public static function init()
    {

        // Attempt to do pre-emptive i18n bootstrapping, in case session locale is available and
        // only non-sitetree actions will be executed this request (e.g. MemberForm::forgotPassword)
        self::install_locale(false);

        // Regenerate routes
        self::regenerate_routes();
    }

    /**
     * Indicates the last locale set via Cookies, in order to prevent excessive Cookie setting
     *
     * @var array Map of [cookie keys => value assigned for that key]
     */
    protected static $last_set_locale = array();

    /**
     * Gets the current locale
     *
     * @param boolean $persist Attempt to persist any detected locale within session / cookies
     * @return string i18n locale code
     */
    public static function current_locale($persist = true)
    {

        // Check overridden locale
        if (self::$_override_locale) {
            return self::$_override_locale;
        }

        // Check direct request
        $locale = self::get_request_locale();

        // Persistant variables
        if (empty($locale)) {
            $locale = self::get_persist_locale();
        }

        // Check browser headers
        if (empty($locale)) {
            $locale = self::detect_browser_locale();
        }

        // Fallback to default if empty or invalid (for this domain)
        $caresAboutDomains = Fluent::is_frontend();
        if (empty($locale) || !in_array($locale, self::locales($caresAboutDomains))) {
            $locale = self::default_locale($caresAboutDomains);
        }

        // Persist locale if requested
        if ($persist) {
            self::set_persist_locale($locale);
        }

        return $locale;
    }

    /**
     * Gets the locale requested directly in the request, either via route, post, or query parameters
     *
     * @return string The locale, if available
     */
    public static function get_request_locale()
    {
        $locale = null;

        // Check controller and current request
        if (Controller::has_curr()) {
            $controller = Controller::curr();
            $request = $controller->getRequest();

            if (self::is_frontend()) {
                // If viewing the site on the front end, determine the locale from the viewing parameters
                $locale = $request->param(self::config()->query_param);
            } else {
                // If viewing the site from the CMS, determine the locale using the session or posted parameters
                $locale = $request->requestVar(self::config()->query_param);
            }
        }

        // Without controller check querystring the old fashioned way
        if (empty($locale) && isset($_REQUEST[self::config()->query_param])) {
            $locale = $_REQUEST[self::config()->query_param];
        }

        return $locale;
    }

    /**
     * Gets the locale currently set within either the session or cookie.
     *
     * @param string $key ID to retrieve persistant locale from. Will automatically detect if omitted.
     * Either Fluent::config()->persist_id or Fluent::config()->persist_id_cms.
     * @return string|null The locale, if available
     */
    public static function get_persist_locale($key = null)
    {
        if (empty($key)) {
            $key = self::is_frontend()
            ? self::config()->persist_id
            : self::config()->persist_id_cms;
        }

        // Skip persist if key is unset
        if (empty($key)) {
            return null;
        }

        // check session then cookies
        if ($locale = Session::get($key)) {
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
     * Not to be confused with the temporary locale assigned with {@see Fluent::with_locale} .
     *
     * @param string $locale Locale to assign
     * @param string $key ID to set the locale against. Will automatically detect if omitted.
     * Either Fluent:::config()->persist_id or Fluent::::config()->persist_id_cms.
     */
    public static function set_persist_locale($locale, $key = null)
    {
        if (empty($key)) {
            $key = self::is_frontend()
            ? self::config()->persist_id
            : self::config()->persist_id_cms;
        }

        // Skip persist if key is unset
        if (empty($key)) {
            return;
        }

        // Save locale
        if ($locale) {
            Session::set($key, $locale);
        } else {
            Session::clear($key);
        }

        // Prevent unnecessarily excessive cookie assigment
        if (!headers_sent() && (
            !isset(self::$last_set_locale[$key]) || self::$last_set_locale[$key] !== $locale
        )) {
            self::$last_set_locale[$key] = $locale;
            Cookie::set($key, $locale, 90, null, null, false, false);
        }
    }

    /**
     * Retrieves the list of locales
     *
     * @param mixed $domain Domain to determine the locales for. If null, the global list be returned.
     * If true, then the current domain will be used.
     * @return array List of locales
     */
    public static function locales($domain = null)
    {
        if ($domain === true) {
            $domain = strtolower($_SERVER['HTTP_HOST']);
        }

        // Check for a domain specific default locale
        if ($domain && ($domains = self::domains()) && !empty($domains[$domain])) {
            $info = $domains[$domain];
            if (!empty($info['locales'])) {
                return $info['locales'];
            }
        }

        // Check for configured locales
        if ($locales = self::config()->locales) {
            return $locales;
        }

        // If no locales are configured, then fallback to the global default locale
        // This should ensure the site is at least usable if a dev/build is performed prior to
        // configuration of `Fluent.locales`
        return array(self::default_locale());
    }

    /**
     * Retrieves the list of locale names as an associative array
     *
     * @return array List of locale names mapped by locale code
     */
    public static function locale_names()
    {
        $locales = array();
        foreach (self::locales() as $locale) {
            $locales[$locale] = i18n::get_locale_name($locale);
        }
        return $locales;
    }

    /**
     *
     * Fetch a native language string from the `i18n` class via a passed locale
     * in the format "XX_xx". In the event a match cannot be found in any framework
     * resource, an empty string is returned.
     *
     * @param string $locale e.g. "pt_BR"
     * @return string The native language string for that locale e.g. "portugu&ecirc;s (Brazil)"
     */
    public static function locale_native_name($locale)
    {
        // Attempts to fetch the native language string via the `i18n::$common_languages` array
        if ($native = i18n::get_language_name(i18n::get_lang_from_locale($locale), true)) {
            return $native;
        }

        // Attempts to fetch the native language string via the `i18n::$common_locales` array
        $commonLocales = i18n::get_common_locales(true);
        if (!empty($commonLocales[$locale])) {
            return $commonLocales[$locale];
        }

        // Nothing else to go on, so return an empty string for a consistent API
        return '';
    }

    /**
     * Determine if the website is in domain segmentation mode
     *
     * @return boolean
     */
    public static function is_domain_mode()
    {

        // Don't act in domain mode if none are configured
        $domains = self::config()->domains;
        if (empty($domains)) {
            return false;
        }

        // Check environment
        if (defined('SS_FLUENT_FORCE_DOMAIN') && SS_FLUENT_FORCE_DOMAIN) {
            return true;
        }

        // Check config
        if (self::config()->force_domain) {
            return true;
        }

        // Check if the current domain is included in the list of configured domains (default)
        return array_key_exists(strtolower($_SERVER['HTTP_HOST']), $domains);
    }

    /**
     * Retrieves any configured domains, assuming the site is running in domain mode.
     *
     * @return array List of domains and their respective configuration information
     */
    public static function domains()
    {

        // Only return configured domains if domain_mode is active. This is typically disabled
        // if there are no domains configured, or if testing locally
        if (self::is_domain_mode()) {
            return self::config()->domains;
        } else {
            return array();
        }
    }

    /**
     * Determine the home domain for this locale
     *
     * @param string $locale
     * @param string|null $domain
     */
    public static function domain_for_locale($locale)
    {
        foreach (self::domains() as $domain => $config) {
            if (!empty($config['locales']) && in_array($locale, $config['locales'])) {
                return $domain;
            }
        }
    }

    /**
     * Retrieves the default locale
     *
     * @param mixed $domain Domain to determine the default locale for. If null, the global default will be returned.
     * If true, then the current domain will be used.
     * @return string
     */
    public static function default_locale($domain = null)
    {
        if ($domain === true) {
            $domain = strtolower($_SERVER['HTTP_HOST']);
        }

        // Check for a domain specific default locale
        if ($domain && ($domains = self::domains()) && !empty($domains[$domain])) {
            $info = $domains[$domain];
            if (!empty($info['default_locale'])) {
                return $info['default_locale'];
            }
            // With no explicitly set default_locale use the first locale assigned
            if (!empty($info['locales'])) {
                return reset($info['locales']);
            }
        }
        return self::config()->default_locale;
    }

    /**
     * Determines behaviour of locale filter in this request, by detecting whether to present an admin view of the
     * site, or a frontend view.
     *
     * If viewing in the CMS items filtered by locale will always be visible, but in the frontend will be filtered
     * as expected.
     *
     * For the sake of unit tests Fluent assumes a frontend execution environment.
     *
     * @param boolean $ignoreController Flag to indicate whether the current controller should be ignored,
     * and detection should be performed by inspecting the URL. Used for testing. Defaults to false.
     * @return boolean Flag indicating if the translation should act on the frontend
     */
    public static function is_frontend($ignoreController = false)
    {

        // No controller - Possibly pre-route phase, so check URL
        if ($ignoreController || !Controller::has_curr()) {
            if (empty($_SERVER['REQUEST_URI'])) {
                return true;
            }

            // $_SERVER['REQUEST_URI'] indeterminately leads with '/', so trim here
            $base = preg_quote(ltrim(Director::baseURL(), '/'), '/');
            return !preg_match('/^(\/)?'.$base.'admin(\/|$)/i', $_SERVER['REQUEST_URI']);
        }

        // Check if controller is aware of its own role
        $controller = Controller::curr();
        if ($controller instanceof ContentController) {
            return true;
        }
        if ($controller->hasMethod('isFrontend')) {
            return $controller->isFrontend();
        }

        // Default to return false for any CMS controller
        return !($controller instanceof AdminRootController)
            && !($controller instanceof LeftAndMain);
    }

    /**
     * Determine if a locale code is within the range of configured locales
     *
     * @param string $locale
     * @return boolean True if this locale is valid
     */
    public static function is_locale($locale)
    {
        if (empty($locale)) {
            return false;
        }
        return in_array($locale, self::locales());
    }

    /**
     * Helper function to check if the value given is present in any of the patterns.
     * This function is case sensitive by default.
     *
     * @param string $value A string value to check against, potentially with parameters (E.g. 'Varchar(1023)')
     * @param array $patterns A list of strings, some of which may be regular expressions
     * @return boolean True if this $value is present in any of the $patterns
     */
    public static function any_match($value, $patterns)
    {
        // Test both explicit value, as well as the value stripped of any trailing parameters
        $valueBase = preg_replace('/\(.*/', '', $value);
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '/') === 0) {
                // Assume valiase prefaced with '/' are regexp
                if (preg_match($pattern, $value) || preg_match($pattern, $valueBase)) {
                    return true;
                }
            } else {
                // Assume simple string comparison otherwise
                if ($pattern === $value || $pattern === $valueBase) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine the alias to use for a specific locale
     *
     * @param string $locale Locale in language_Country format
     * @return string Locale in its original form, or its alias if one exists
     */
    public static function alias($locale)
    {
        $aliases = self::config()->aliases;
        return empty($aliases[$locale])
            ? $locale
            : $aliases[$locale];
    }

    /**
     * Determine the DB field name to use for the given base field
     *
     * @param string $field DB field name
     * @param string $locale Locale to use
     */
    public static function db_field_for_locale($field, $locale)
    {
        return "{$field}_{$locale}";
    }

    /**
     * Installs the current locale into i18n
     *
     * @param boolean $persist Attempt to persist any detected locale within session / cookies
     */
    public static function install_locale($persist = true)
    {

        // Ensure the locale is set correctly given the designated parameters
        $locale = self::current_locale($persist);
        if (empty($locale)) {
            return;
        }

        i18n::set_locale($locale);

        // LC_NUMERIC causes SQL errors for some locales (comma as decimal indicator) so skip
        foreach (array(LC_COLLATE, LC_CTYPE, LC_MONETARY, LC_TIME) as $category) {
            setlocale($category, "{$locale}.UTF-8", $locale);
        }

        // Get date/time formats from Zend
        require_once 'Zend/Date.php';
        i18n::config()->date_format = Zend_Locale_Format::getDateFormat($locale);
        i18n::config()->time_format = Zend_Locale_Format::getTimeFormat($locale);
    }

    /**
     * Determines the locale best matching the given list of browser locales
     *
     * @param mixed $domain Domain to determine the locales for. If null, the global list be returned.
     * If true, then the current domain will be used.
     * @return string The matching locale, or null if none could be determined
     */
    public static function detect_browser_locale($domain = null)
    {

        // Check for empty case
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        // Given multiple canditates, narrow down the final result using the client's preferred languages
        $inputLocales = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
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

        $prioritisedLocales = array();
        if (count($parsedLocales['code'])) {
            // create a list like "en" => 0.8
            $parsedLocales = array_combine($parsedLocales['code'], $parsedLocales['priority']);

            // Generate nested list of priorities => [locales]
            foreach ($parsedLocales as $locale => $priority) {
                $priority = empty($priority) ? 1.0 : floatval($priority);
                if (empty($prioritisedLocales[$priority])) {
                    $prioritisedLocales[$priority] = array();
                }
                $prioritisedLocales[$priority][] = $locale;
            }

            // sort list based on value
            krsort($prioritisedLocales, SORT_NUMERIC);
        }

        // Check each requested locale against loaded locales
        foreach ($prioritisedLocales as $priority => $parsedLocales) {
            foreach ($parsedLocales as $browserLocale) {
                foreach (self::locales($domain) as $locale) {
                    if (stripos(preg_replace('/_/', '-', $locale), $browserLocale) === 0) {
                        return $locale;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Temporary override variable
     *
     * @var boolean
     */
    private static $_override_locale = null;

    /**
     * Executes a callback with a locale to temporarily emulate.
     *
     * Warning: Existing DataObjects will contain fields in the actual locale if already lazily loaded, or if
     * used within the callback, will populate itself with the overriding locale. The inverse will occur once the
     * callback is complete. The best practice is to consider this a sandbox, and re-requery all objects required,
     * discarding these afterwards.
     *
     * @param string $locale The locale to set
     * @param callable $callback The callback
     * @return mixed The returned value from the $callback
     */
    public static function with_locale($locale, $callback)
    {

        // Check and set locale
        if (self::$_override_locale) {
            throw new BadMethodCallException("Fluent::with_locale cannot be nested");
        }
        if (!in_array($locale, self::locales())) {
            throw new BadMethodCallException("Invalid locale $locale");
        }
        self::$_override_locale = $locale;
        DataObject::flush_and_destroy_cache();

        // Callback
        $result = call_user_func($callback);

        // Reset
        self::$_override_locale = null;
        DataObject::flush_and_destroy_cache();
        return $result;
    }

    /**
     * Cached search adapter
     *
     * @var FluentSearchAdapter
     */
    protected static $_search_dapter = null;

    /**
     * Retrieves a search adapter for the current database adapter
     *
     * @return FluentSearchAdapter
     */
    public static function search_adapter()
    {
        if (self::$_search_dapter) {
            return self::$_search_dapter;
        }
        foreach (self::config()->search_adapters as $connector => $adapter) {
            if ($connector && $adapter && DB::getConn() instanceof $connector) {
                return self::$_search_dapter = new $adapter();
            }
        }
    }

    /**
     * Determine the baseurl within a specified $locale.
     *
     * @param string $locale Locale, or null to use current locale
     * @return string
     */
    public static function locale_baseurl($locale = null)
    {
        if (empty($locale)) {
            $locale = Fluent::current_locale();
        }

        // Build domain-specific base url
        $base = Director::baseURL();
        if ($domain = Fluent::domain_for_locale($locale)) {
            $base = Controller::join_links(Director::protocol().$domain, $base);
        }

        // Don't append locale to home page for default locale
        if ($locale === self::default_locale()) {
            return $base;
        }

        // Append locale otherwise
        return Controller::join_links(
            $base,
            self::alias($locale),
            '/'
        );
    }

    /**
     * @return array
     */
    public static function get_template_global_variables()
    {
        return array(
            'CurrentLocale' => array(
                'method' => 'current_locale',
                'casting' => 'Text'
            )
        );
    }
}
