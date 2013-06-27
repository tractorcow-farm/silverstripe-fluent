<?php

/**
 * Data extension class for a class which should only be present in one or more locales
 * 
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentFilteredExtension extends DataExtension {
	
	public static function get_extra_config($class, $extension, $args) {
		
		// Create a separate boolean field to indicate visibility in each field
		$db = array();
		$defaults = array();
		
		foreach(Fluent::locales() as $locale) {
			$field = "LocaleFilter_{$locale}";
			// Copy field to translated field
			$db[$field] = 'Boolean(1)';
			$defaults[$field] = '1';
		}
		
		return array(
			'db' => $db,
			'defaults' => $defaults
		);
	}
	
	function updateCMSFields(FieldList $fields) {
		
		// Present a set of checkboxes for filtering this item by locale
		$filterField = new FieldGroup();
		$filterField->setTitle('Locale filter');
		foreach(Fluent::locales() as $locale) {
			$filterField->push(new CheckboxField("LocaleFilter_{$locale}", $locale, 1));
		}
		$filterField->push($descriptionField = new LiteralField(
			'LocaleFilterDescription',
			'<em>Check a locale to show this item on that locale</em>'
		));
		
		if($fields->hasTabSet()) {
			$fields->addFieldToTab('Root.Locales', $filterField);
		} else {
			$fields->add($filterField);
		}
	}
	
	public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		
		// Skip ID based filters
		if($query->filtersOnID()) return;
		
		// Skip filter in the CMS
		if(!Fluent::is_frontend()) return;
		
		// Add filter for locale
		$locale = Fluent::current_locale();
		$query->addWhere("\"$this->ownerBaseClass\".\"LocaleFilter_{$locale}\" = 1");
	}
}
