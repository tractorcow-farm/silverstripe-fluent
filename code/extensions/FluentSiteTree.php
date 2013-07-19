<?php

/**
 * SiteTree extension class for translatable objects
 * 
 * @see SiteTree
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentSiteTree extends FluentExtension {
	
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
	 * Determine the link to this page given the specified $locale
	 * 
	 * @param string $locale
	 * @return string
	 */
	public function LocaleLink($locale) {
		$link = $this->owner->Link();
		$currentURL = Fluent::alias(Fluent::current_locale());
		$localeURL = Fluent::alias($locale);
		return preg_replace('/^\/'.$currentURL.'/i', $localeURL, $link);
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
				'Title' => i18n::get_locale_name($locale),
				'Link' => $this->LocaleLink($locale)
			));
		}
		return new ArrayList($data);
	}
}
