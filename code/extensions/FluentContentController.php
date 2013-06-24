<?php

/**
 * Fluent extension for ContentController
 * 
 * @see ContentController
 */
class FluentContentController extends Extension {
	function onBeforeInit() {
		// Ensure the locale is set correctly given the designated parameters
		$locale = Fluent::current_locale();
		i18n::set_locale($locale);
		
		// Get date/time formats from Zend
		require_once 'Zend/Date.php';
		i18n::set_date_format(Zend_Locale_Format::getDateFormat($locale));
		i18n::set_time_format(Zend_Locale_Format::getTimeFormat($locale));
	}
}
