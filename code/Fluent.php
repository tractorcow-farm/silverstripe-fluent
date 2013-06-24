<?php

/**
 * Bootstrapping and configuration object for Fluet localisation
 */
class Fluent extends Object {
	
	/**
	 * Determine if routes are invalid and require regeneration
	 */
	protected static function routes_invalidated() {
		
		// If flushing the application, force routes to become invalidated
		if(	!empty($_GET['flush']) || isset($_REQUEST['url'])
			&& ($_REQUEST['url'] == 'dev/build' || $_REQUEST['url'] == BASE_URL . '/dev/build')
		) {
			return true;
		}
		
		// If routes are not initialised then generate
		if(empty(self::config()->routes)) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Forces regeneration of all locale routes
	 */
	public static function regenerate_routes() {
		$routes = array();
		
		// Explicit routes
		foreach(self::config()->locales as $locale) {
			$routes[$locale.'/$URLSegment!//$Action/$ID/$OtherID'] = array(
				'Controller' => 'ModelAsController',
				'Locale' => $locale
			);
			$routes[$locale] = array(
				'Controller' => 'FluentRootURLController',
				'Locale' => $locale
			);
		}
		
		// Default route
		$routes[''] = 'FluentRootURLController';
		
		// Set routes locally
		self::config()->routes = $routes;
		
		// Load into core routes
		Config::inst()->update('Director', 'rules', $routes);
	}
	
	/**
	 * Initialise routes
	 */
	public static function init() {
		
		// Allow route generation to be turned off
		if(!self::config()->generate_routes) return;
		
		// Determine if routes are valid
		if(!self::routes_invalidated()) return;
		
		// Regenerate routes
		self::regenerate_routes();
	}
	
	/**
	 * Gets the current locale
	 * 
	 * @return string i18n locale code
	 */
	public static function current_locale() {
		
		if(!Controller::has_curr()) return Fluent::config()->default_locale;
		
		$controller = Controller::curr();
		$request = $controller->getRequest();
		$locale = null;
		
		if(self::is_frontend()) {
			// If viewing the site on the front end, determine the locale from the viewing parameters
			$locale = $request->param('Locale');
		}
		
		if(empty($locale)) {
			// If viewing the site from the CMS, determine the locale using the session or posted parameters
			$locale = $request->requestVar('Locale');
		}
		
		// check session
		if(empty($locale)) $locale = Session::get('Locale');
		
		// Check cookies
		if(empty($locale)) $locale = Cookie::get('Locale');
		
		// Check result
		if(empty($locale)) $locale = Fluent::config()->default_locale;
		
		// Save locale
		if(!headers_sent()) {
			Session::set('Locale', $locale);
			Cookie::set('Locale', $locale);
		}
		return $locale;
	}
	
	/**
	 * Determines field replacement method.
	 * If viewing on the front end, blank values for a translation will be replaced with the default value.
	 * If viewing in the CMS they will be left blank for filling in
	 * 
	 * @return boolean Flag indicating if the translation should act on the frontend
	 */
	public static function is_frontend() {
		if(!Controller::has_curr()) return false;
		$controller = Controller::curr();
		return ($controller instanceof ModelAsController || $controller instanceof ContentController);
	}
	
	/**
	 * Helper function to check if the value given is present in any of the patterns
	 * 
	 * @param string $value
	 * @param array $patterns
	 * @return boolean
	 */
	public static function any_match($value, $patterns) {
		foreach($patterns as $pattern) {
			if(strpos($pattern, '/') === 0) {
				if(preg_match($pattern, $value)) return true;
			} else {
				if($value === $pattern) return true;
			}
		}
		return false;
	}
}
