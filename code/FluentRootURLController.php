<?php

/**
 * Home page controller for multiple locales
 * 
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentRootURLController extends RootURLController {
	
	/**
	 * Determine if the referrer for this request is from a domain within this website's scope
	 * 
	 * @return boolean
	 */
	protected function knownReferrer() {
		
		// Extract referrer
		if(empty($_SERVER['HTTP_REFERER'])) return false;
		$hostname = strtolower(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST));
		
		// Check configured domains
		$domains = Fluent::domains();
		if(empty($domains)) {
			return $hostname == strtolower($_SERVER['HTTP_HOST']);
		} else {
			return isset($domains[$hostname]);	
		}
	}
	
	public function handleRequest(SS_HTTPRequest $request, DataModel $model = null) {
		
		self::$is_at_root = true;
		$this->setDataModel($model);
		
		$this->pushCurrent();
		$this->init();
		$this->setRequest($request);
		
		// Check for existing routing parameters, redirecting to another locale automatically if necessary
		$locale = Fluent::get_request_locale();
		if(empty($locale)) {
			
			// If visiting the site for the first time, redirect the user to the best locale
			// This can also interfere with flushing, so don't redirect in this case either
			// Limit this search to the current domain, preventing cross-domain redirection
			if( Fluent::config()->detect_locale
				&& !isset($_GET['flush'])
				&& (Fluent::get_persist_locale() == null)
				&& ($locale = Fluent::detect_browser_locale(true)) !== Fluent::default_locale(true)
				&& !$this->knownReferrer()
			) {
				// Redirect to best locale
				return $this->redirect(Fluent::locale_baseurl($locale));
			} 
			
			// Reset parameters to act in the default locale
			$locale = Fluent::default_locale(true);
			Fluent::set_persist_locale($locale);
			$params = $request->routeParams();
			$params[Fluent::config()->query_param] = $locale;
			$request->setRouteParams($params);
		}
		
		if(!DB::isActive() || !ClassInfo::hasTable('SiteTree')) {
			$this->response = new SS_HTTPResponse();
			$this->response->redirect(Director::absoluteBaseURL() . 'dev/build?returnURL=' . (isset($_GET['url']) ? urlencode($_GET['url']) : null));
			return $this->response;
		}
		
		$localeURL = Fluent::alias($locale);
		$request->setUrl($localeURL.'/home/');
		$request->match($localeURL.'/$URLSegment//$Action', true);
		
		$controller = new ModelAsController();
		$result = $controller->handleRequest($request, $model);
		
		$this->popCurrent();
		return $result;
	}
}
