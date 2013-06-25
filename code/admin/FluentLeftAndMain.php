<?php

/**
 * Fluent extension for main CMS admin
 * 
 * @see LeftAndMain
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentLeftAndMain extends LeftAndMainExtension {
	public function init() {
		$locales = json_encode(Fluent::locale_names());
		$locale = json_encode(Fluent::current_locale());
		Requirements::customScript("var fluentLocales = $locales;var fluentLocale = $locale;", 'FluentHeadScript');
		Requirements::javascript(basename(dirname(dirname(dirname(__FILE__)))) . '/javascript/fluent.js');
	}
}