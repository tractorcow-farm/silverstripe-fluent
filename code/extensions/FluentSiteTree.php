<?php

/**
 * SiteTree extension class for translatable objects
 * 
 * @see SiteTree
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentSiteTree extends FluentExtension {
	
	/**
	 * Ensure that the controller is correctly initialised
	 * 
	 * @param ContentController $controller
	 */
	public function contentcontrollerInit($controller) {
		Fluent::install_locale();
	}
	
	public function updateRelativeLink(&$base, &$action) {
		
		// Don't inject locale to subpages
		if($this->owner->ParentID && SiteTree::config()->nested_urls) {
			return;
		}
		
		// For blank/temp pages such as Security controller fallback to querystring
		$locale = Fluent::current_locale();
		if(!$this->owner->exists()) {
			$base = Controller::join_links($base, '?'.Fluent::config()->query_param.'='.urlencode($locale));
			return;
		}
		
		// For home page in the default locale, do not alter home url
		if($base === null) {
			// Check if this locale is the root of its home domain
			$domain = Fluent::domain_for_locale($locale);
			if($locale === Fluent::default_locale($domain)) {
				return;
			}
		}
		
		// Simply join locale root with base relative URL
		$localeURL = Fluent::alias($locale);
		$base = Controller::join_links($localeURL, $base);
	}
	
	public function LocaleLink($locale) {
		
		// For blank/temp pages such as Security controller fallback to querystring
		if(!$this->owner->exists()) {
			$url = Controller::curr()->getRequest()->getURL();
			return Controller::join_links($url, '?'.Fluent::config()->query_param.'='.urlencode($locale));
		}
		
		return parent::LocaleLink($locale);
	}
	
	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);
		
		// Fix URLSegment field issue for root pages
		if(!SiteTree::config()->nested_urls || empty($this->owner->ParentID)) {
			$baseLink = Director::absoluteURL(Controller::join_links(
				Director::baseURL(),
				Fluent::alias(Fluent::current_locale()),
				'/'
			));
			$urlsegment = $fields->dataFieldByName('URLSegment');
			$urlsegment->setURLPrefix($baseLink);
		}
	}
}
