<?php

/**
 * Home page controller for multiple locales
 */
class FluentRootURLController extends RootURLController {
	
	public function handleRequest(SS_HTTPRequest $request, DataModel $model = null) {
		
		self::$is_at_root = true;
		$this->setDataModel($model);
		
		// Check locale, redirecting to locale root if necessary
		$locale = $request->param('Locale');
		if(empty($locale)) {
			$locale = Fluent::config()->default_locale;
			$this->redirect($locale.'/');
			return $this->response;
		}
		
		$this->pushCurrent();
		$this->init();
		
		if(!DB::isActive() || !ClassInfo::hasTable('SiteTree')) {
			$this->response = new SS_HTTPResponse();
			$this->response->redirect(Director::absoluteBaseURL() . 'dev/build?returnURL=' . (isset($_GET['url']) ? urlencode($_GET['url']) : null));
			return $this->response;
		}
		
		$request->setUrl($locale.'/home/');
		$request->match($locale.'/$URLSegment//$Action', true);
		
		$controller = new ModelAsController();
		$result = $controller->handleRequest($request, $model);
		
		$this->popCurrent();
		return $result;
	}
}
