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
		$locale = Fluent::current_locale();
		$localeURL = Fluent::alias($locale);
		$base = Controller::join_links($localeURL, $base);
	}
	
	/**
	 * Determine the link to this page given the specified $locale.
	 * Override this in your Page class to customise.
	 * 
	 * @param string $locale
	 * @return string
	 */
	public function LocaleLink($locale) {
		$link = $this->owner->Link();
		$currentURL = Controller::join_links(Director::baseURL(), Fluent::alias(Fluent::current_locale()));
		$localeURL = Controller::join_links(Director::baseURL(), Fluent::alias($locale));
		return preg_replace('/^('.preg_quote($currentURL, '/').')/i', $localeURL, $link);
	}
	
	/**
	 * Determine the baseurl within a specified $locale.
	 *
	 * @return string
	 */
	public function BaseURLForLocale() {
		return Controller::join_links(
			Director::baseURL(),
			Fluent::alias(Fluent::current_locale()),
			'/'
		);
	}
	
	/**
	 * Templatable list of all locales
	 * 
	 * @return ArrayList
	 */
	public function Locales() {
		$data = array();
		foreach(Fluent::locales() as $locale) {
			$data[] = new ArrayData(array(
				'Locale' => $locale,
				'Alias' => Fluent::alias($locale),
				'Title' => i18n::get_locale_name($locale),
				'Link' => $this->owner->LocaleLink($locale)
			));
		}
		return new ArrayList($data);
	}
}
