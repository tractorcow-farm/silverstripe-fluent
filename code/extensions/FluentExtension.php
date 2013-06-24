<?php

class FluentExtension extends DataExtension {
	
	/**
	 * Hook allowing temporary disabling of extra fluent fields
	 *
	 * @var boolean
	 */
	protected static $disable_fluent_fields = false;
	
	/**
	 * Executes a callback with extra fluent fields disabled
	 * 
	 * @param callback $callback
	 * @return mixed
	 */
	protected static function without_fluent_fields($callback) {
		self::$disable_fluent_fields = true;
		$result = $callback();
		self::$disable_fluent_fields = false;
		return $result;
	}
	
	/**
	 * Determines the fields to translate on the given class
	 * 
	 * @return array List of field names and data types
	 */
	public static function translated_fields_for($class) {
		return self::without_fluent_fields(function() use ($class) {
			$db = Config::inst()->get($class, 'db', Config::UNINHERITED);
			$filter = Config::inst()->get($class, 'translate', Config::UNINHERITED);
			
			// Data and field filters
			$fieldsInclude = Fluent::config()->field_include;
			$fieldsExclude = Fluent::config()->field_exclude;
			$dataInclude = Fluent::config()->data_include;
			$dataExclude = Fluent::config()->data_exclude;
			
			// filter out DB
			if($db) foreach($db as $field => $type) {
				if(!empty($filter)) {
					// If given an explicit field name filter, then remove non-presented fields
					if(!in_array($field, $filter)) {
						unset($db[$field]);
					}
				} else {
					// Split out arguments from type specifications to get the DBField class name
					$type = preg_replace('/\(.*/', '', $type);
					
					// Without a name filter then check against each filter type
					if(	($fieldsInclude && !Fluent::any_match($field, $fieldsInclude))
						|| ($fieldsExclude && Fluent::any_match($field, $fieldsExclude))
						|| ($dataInclude && !Fluent::any_match($type, $dataInclude))
						|| ($dataExclude && Fluent::any_match($type, $dataExclude))
					) {
						unset($db[$field]);
					}
				}
			}
			
			return $db;
		});
	}
	
	/**
	 * Generates the extra DB fields for a class (not including subclasses)
	 * 
	 * @param string $class
	 */
	public static function generate_extra_config($class) {
		$baseFields = self::translated_fields_for($class);
		
		$db = array();
		
		if($baseFields) foreach($baseFields as $field => $type) {
			foreach(Fluent::config()->locales as $locale) {
				// Copy field to translated field
				$db["{$field}_{$locale}"] = $type;
			}
		}
		
		return $db;
	}
	
	public static function get_extra_config($class, $extension, $args) {
		if(self::$disable_fluent_fields) return array();
		
		// Merge all config values for subclasses
		foreach (ClassInfo::subclassesFor($class) as $subClass) {
			$db = self::generate_extra_config($subClass);
			Config::inst()->update($subClass, 'db', $db);
		}
		
		return array(
			'db' => self::generate_extra_config($class)
		);
	}
	
	/**
	 * Templatable list of all locales
	 * 
	 * @return ArrayList
	 */
	public function Locales() {
		$data = array();
		foreach(Fluent::config()->locales as $locale) {
			$data[] = new ArrayData(array(
				'Locale' => $locale,
				'Title' => i18n::get_locale_name($locale)
			));
		}
		return new ArrayList($data);
	}
}
