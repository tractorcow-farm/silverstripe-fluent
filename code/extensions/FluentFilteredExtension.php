<?php

/**
 * Data extension class for a class which should only be present in one or more locales
 * 
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentFilteredExtension extends DataExtension {
	
	/**
	 * Set the filter of locales to the specified locale, or array of locales
	 * 
	 * @param string|array $locale Locale, or list of locales
	 * @param string $locale... Additional locales
	 */
	public function setFilteredLocales($locales) {
		$locales = is_array($locales) ? $locales : func_get_args();
		foreach(Fluent::locales() as $locale) {
			$field = Fluent::db_field_for_locale("LocaleFilter", $locale);
			$this->owner->$field = in_array($locale, $locales);
		}
	}
	
	/**
	 * Gets the list of locales this items is filtered against
	 * 
	 * @param boolean $visible Set to false to get excluded instead of included locales
	 * @return array List of locales
	 */
	public function getFilteredLocales($visible = true) {
		$locales = array();
		foreach(Fluent::locales() as $locale) {
			if($this->owner->canViewInLocale($locale) == $visible) {
				$locales[] = $locale;
			}
		}
		return $locales;
	}
	
	/**
	 * Determine if this object is visible (or excluded) in the specified locale
	 * 
	 * @param string $locale Locale to check against
	 * @return boolean True if the object is visible in the specified locale
	 */
	public function canViewInLocale($locale) {
		$field = Fluent::db_field_for_locale("LocaleFilter", $locale);
		return $this->owner->$field;
	}
	
	public static function get_extra_config($class, $extension, $args) {
		
		// Create a separate boolean field to indicate visibility in each field
		$db = array();
		$defaults = array();
		
		foreach(Fluent::locales() as $locale) {
			$field = Fluent::db_field_for_locale("LocaleFilter", $locale);
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
		$filterField->setTitle(_t('Fluent.LocaleFilter', 'Locale Filter'));
		foreach(Fluent::locales() as $locale) {
			$id = Fluent::db_field_for_locale("LocaleFilter", $locale);
			$title = i18n::get_locale_name($locale);
			$filterField->push(new CheckboxField($id, $title));
		}
		$filterField->push($descriptionField = new LiteralField(
			'LocaleFilterDescription',
			_t('Fluent.LocaleFilterDescription', '<em>Check a locale to show this item on that locale</em>')
		));

		if($fields->hasTabSet()) {
			$fields->findOrMakeTab('Root.Locales', _t('Fluent.TABLOCALES', 'Locales'));
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
