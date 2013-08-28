<?php

/**
 * Home page controller for multiple locales
 * 
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentRootURLController extends RootURLController {
	
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
			if( !isset($_GET['flush'])
				&& (Fluent::get_persist_locale() == null)
				&& ($locale = Fluent::current_locale()) !== Fluent::default_locale()
			) {
				// Redirect to best locale
				return $this->redirect(Fluent::locale_baseurl($locale));
			} else {
				// Reset parameters to act in the default locale
				$locale = Fluent::default_locale();
				Fluent::set_persist_locale($locale);
				$params = $request->routeParams();
				$params[Fluent::config()->query_param] = $locale;
				$request->setRouteParams($params);
			}
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
