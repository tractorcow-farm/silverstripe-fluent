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
		$locale = Fluent::current_locale();
		$base = Controller::join_links($locale, $base);
	}
	
	/**
	 * Determine the link to this page given the specified $locale
	 * 
	 * @param string $locale
	 * @return string
	 */
	public function LocaleLink($locale) {
		$link = $this->owner->Link();
		$currentLocale = Fluent::current_locale();
		return preg_replace('/^\/'.$currentLocale.'/i', $locale, $link);
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
