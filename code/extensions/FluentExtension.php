<?php

/**
 * Data extension class for translatable objects
 * 
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class FluentExtension extends DataExtension {
	
	// <editor-fold defaultstate="collapsed" desc="Field helpers">
	
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
		$before = self::$disable_fluent_fields;
		self::$disable_fluent_fields = true;
		$result = $callback();
		self::$disable_fluent_fields = $before;
		return $result;
	}
	
	/**
	 * Cache for list of translated fields for all inspected classes
	 *
	 * @var array
	 */
	protected static $translated_fields_for_cache = array();
	
	/**
	 * Determines the fields to translate on the given class
	 * 
	 * @return array List of field names and data types
	 */
	public static function translated_fields_for($class) {
		if(isset(self::$translated_fields_for_cache[$class])) {
			return self::$translated_fields_for_cache[$class];
		}
		return self::$translated_fields_for_cache[$class] = self::without_fluent_fields(function() use ($class) {
			$db = DataObject::custom_database_fields($class);
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
	 * Get all database tables in the class ancestry and their respective
	 * translatable fields
	 * 
	 * @return array
	 */
	protected function getTranslatedTables() {
		$includedTables = array();
		foreach($this->owner->getClassAncestry() as $class) {
			
			// Skip classes without tables
			if(!DataObject::has_own_table($class)) continue;
			
			// Check translated fields for this class
			$translatedFields = self::translated_fields_for($class);
			if(empty($translatedFields)) continue;
			
			// Mark this table as translatable
			$includedTables[$class] = array_keys($translatedFields);
		}
		return $includedTables;
	}

	// </editor-fold>
	
	// <editor-fold defaultstate="collapsed" desc="Database Field Generation">
	
	/**
	 * Generates the extra DB fields for a class (not including subclasses)
	 * 
	 * @param string $class
	 */
	public static function generate_extra_config($class) {
		$baseFields = self::translated_fields_for($class);
		
		$db = array();
		
		if($baseFields) foreach($baseFields as $field => $type) {
			foreach(Fluent::locales() as $locale) {
				// Transform has_one relations into basic int fields to prevent interference with ORM
				if($type === 'ForeignKey') $type = 'Int';
				$translatedName = Fluent::db_field_for_locale($field, $locale);
				$db[$translatedName] = $type;;
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
		
		// Force all subclass DB caches to invalidate themselves since their db attribute is now expired
		DataObject::reset();
		
		return array(
			'db' => self::generate_extra_config($class)
		);
	}
	
	// </editor-fold>
	
	// <editor-fold defaultstate="collapsed" desc="Template Accessors">
	/**
	 * Templatable list of all locales
	 * 
	 * @return ArrayList
	 */
	public function Locales() {
		$data = array();
		foreach (Fluent::locales() as $locale) {
			$data[] = new ArrayData(array(
				'Locale' => $locale,
				'Alias' => Fluent::alias($locale),
				'Title' => i18n::get_locale_name($locale)
			));
		}
		return new ArrayList($data);
	}
	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="SQL Augmentations">
	
	protected static $_enable_write_augmentation = true;
	
	/*
	 * Enable or disable write augmentations. Useful for setting up test cases with specific hard coded values.
	 * 
	 * @param boolean $enabled
	 */
	public static function set_enable_write_augmentation($enabled) {
		self::$_enable_write_augmentation = $enabled;
	}
	
	/**
	 * Determines the table/column identifier that first appears in the $condition, and returns the localised version
	 * of that column.
	 * 
	 * @param string $condition Condition SQL string
	 * @param array $includedTables
	 * @param string $locale Locale to localise to
	 * @return string Column identifier in "table"."column" format if it exists in this condition
	 */
	protected function detectFilterColumn($condition, $includedTables, $locale) {
		foreach($includedTables as $table => $columns) {
			foreach($columns as $column) {
				$identifier = "\"$table\".\"$column\"";
				if(stripos($condition, $identifier) !== false) {
					// Localise column
					return "\"$table\".\"".Fluent::db_field_for_locale($column, $locale)."\"";
				}
			}
		}
		return false;
	}
	
	/**
	 * Replaces all columns in the given condition with any localised
	 * 
	 * @param string $condition Condition SQL string
	 * @param array $includedTables
	 * @param string $locale Locale to localise to
	 * @return string $condition parameter with column names replaced
	 */
	protected function localiseFilterCondition($condition, $includedTables, $locale) {
		foreach($includedTables as $table => $columns) {
			foreach($columns as $column) {
				$columnLocalised = Fluent::db_field_for_locale($column, $locale);
				$identifier = "\"$table\".\"$column\"";
				$identifierLocalised = "\"$table\".\"$columnLocalised\"";
				$condition = preg_replace("/".preg_quote($identifier, '/')."/", $identifierLocalised, $condition);
			}
		}
		return $condition;
	}

	public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		
		// Get locale and translation zone to use
		$dataQuery->setQueryParam('Fluent.Locale', $locale = Fluent::current_locale());
		$dataQuery->setQueryParam('Fluent.IsFrontend', Fluent::is_frontend());
		
		// Get all tables to translate fields for, and their respective field names
		$includedTables = $this->getTranslatedTables();
		
		// Iterate through each select clause, replacing each with the translated version
		foreach($query->getSelect() as $alias => $select) {
			
			// Skip fields without table context
			if(!preg_match('/^"(?<class>\w+)"\."(?<field>\w+)"$/i', $select, $matches)) continue;
			
			$class = $matches['class'];
			$field = $matches['field'];

			// If this table doesn't have translated fields then skip
			if(empty($includedTables[$class])) continue;

			// If this field shouldn't be translated, skip
			if(!in_array($field, $includedTables[$class])) continue;

			$translatedField = Fluent::db_field_for_locale($field, $locale);
			$expression = "CASE
				WHEN (\"{$class}\".\"{$translatedField}\" IS NOT NULL AND \"{$class}\".\"{$translatedField}\" != '')
				THEN \"{$class}\".\"{$translatedField}\"
				ELSE \"$class\".\"$field\" END";
			$query->selectField($expression, $alias);
		}
		
		// Rewrite where conditions
		$where = $query->getWhere();
		foreach($where as $index => $condition) {
			
			// determine the table/column this condition is against
			$filterColumn = $this->detectFilterColumn($condition, $includedTables, $locale);
			if(empty($filterColumn)) continue;
			
			// Duplicate the condition with all localisable fields replaced
			$localisedCondition = $this->localiseFilterCondition($condition, $includedTables, $locale);
			if($localisedCondition === $condition) continue;
			
			// Generate new condition that conditionally executes one of the two conditions
			// depending on field nullability
			$where[$index] = "
				($filterColumn IS NOT NULL AND $filterColumn != '' AND ($localisedCondition))
				OR (
					($filterColumn IS NULL OR $filterColumn = '') AND ($condition)
				)";
		}
		$query->setWhere($where);
	}

	public function augmentWrite(&$manipulation) {
		
		// Bypass augment write if requested
		if(!self::$_enable_write_augmentation) return;
		
		// Get locale and translation zone to use
		$locale = Fluent::current_locale();
		$locales = Fluent::locales();
							
		// Get all tables to translate fields for, and their respective field names
		$includedTables = $this->getTranslatedTables();
		
		// Iterate through each select clause, replacing each with the translated version
		foreach($manipulation as $class => $updates) {

			// If this table doesn't have translated fields then skip
			if(empty($includedTables[$class])) continue;
			
			foreach($includedTables[$class] as $field) {

				// Unset any direct translation updates
				foreach($locales as $checkLocale) {
					$checkField = Fluent::db_field_for_locale($field, $checkLocale);
					if(isset($updates['fields'][$checkField])) {
						unset($updates['fields'][$checkField]);
					}
				}
				
				// Check if this field is updated
				if(isset($updates['fields'][$field])) {
					// Copy the updated value to the appropriate locale
					$updateField = Fluent::db_field_for_locale($field, $locale);
					$updates['fields'][$updateField] = $updates['fields'][$field];
					
					// If not on the default locale we should prevent the default field being written to
					// unless it's an insert
					if($locale !== Fluent::default_locale() && $updates['command'] === 'update') {
						unset($updates['fields'][$field]);
					}
				}
			}
			
			// Save back modifications to the manipulation
			$manipulation[$class] = $updates;
		}
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="CMS Field Augmentation">
	
	public function updateCMSFields(FieldList $fields) {
		// get all fields to translate and remove
		$translated = $this->getTranslatedTables();
		foreach($translated as $table => $translatedFields) {
			foreach($translatedFields as $translatedField) {
				foreach(Fluent::locales() as $locale) {
					// Remove translation DBField from automatic scaffolded fields
					$fieldName = Fluent::db_field_for_locale($translatedField, $locale);
					$fields->removeByName($fieldName, true);
				}
			}
		}
	}
	
	// </editor-fold>
	
}
