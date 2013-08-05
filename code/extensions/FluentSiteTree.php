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
		$currentURL = $this->owner->BaseURLForLocale();
		$localeURL = $this->owner->BaseURLForLocale($locale);
		return preg_replace('/^('.preg_quote($currentURL, '/').')/i', $localeURL, $link);
	}
	
	/**
	 * Determine the baseurl within a specified $locale.
	 *
	 * @param string $locale Locale, or null to use current locale
	 * @return string
	 */
	public function BaseURLForLocale($locale = null) {
		if(empty($locale)) $locale = Fluent::current_locale();
		return Controller::join_links(
			Director::baseURL(),
			Fluent::alias($locale),
			'/'
		);
	}
	
	/**
	 * Templatable list of all locales
	 * 
	 * @return ArrayList
	 */
	public function Locales() {
		// Check any locale this page is not available in
		$invalidLocales = $this->owner->hasMethod('getFilteredLocales')
				? $this->owner->getFilteredLocales(true)
				: array();
		
		$data = array();
		foreach(Fluent::locales() as $locale) {
			
			// Check url: Invalid urls should point to the baseurl for that locale
			$link = in_array($locale, $invalidLocales)
					? $this->owner->BaseURLForLocale($locale)
					: $this->owner->LocaleLink($locale);
			
			// Check linking mode
			$linkingMode = 'link';
			if(in_array($locale, $invalidLocales)) {
				$linkingMode = 'invalid';
			} elseif($locale === Fluent::current_locale()) {
				$linkingMode = 'current';
			}
			
			// Build object
			$data[] = new ArrayData(array(
				'Locale' => $locale,
				'Alias' => Fluent::alias($locale),
				'Title' => i18n::get_locale_name($locale),
				'Link' => $link,
				'LinkingMode' => $linkingMode
			));
		}
		return new ArrayList($data);
	}
}
