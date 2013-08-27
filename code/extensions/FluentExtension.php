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
	
	public static function base_indexes($class) {
		return self::without_fluent_fields(function() use ($class) {
			return Config::inst()->get($class, 'indexes', Config::UNINHERITED);
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

	/**
	 * Splits a spec string safely, considering quoted columns, whitespace, 
	 * and cleaning brackets
	 * 
	 * @param string $spec The input index specification
	 * @return array List of columns in the spec
	 */
	protected static function explode_column_string($spec) {
		// Remove any leading/trailing brackets and outlying modifiers
		// E.g. 'unique (Title, "QuotedColumn");' => 'Title, "QuotedColumn"'
		$containedSpec = preg_replace('/(.*\(\s*)|(\s*\).*)/', '', $spec);

		// Split potentially quoted modifiers
		// E.g. 'Title, "QuotedColumn"' => array('Title', 'QuotedColumn')
		return preg_split('/"?\s*,\s*"?/', trim($containedSpec, '(") '));
	}

	/**
	 * Builds a properly quoted column list from an array
	 * 
	 * @param array $columns List of columns to implode
	 * @return string A properly quoted list of column names
	 */
	protected static function implode_column_list($columns) {
		if(empty($columns)) return '';
		return '"' . implode('","', $columns) . '"';
	}

	/**
	 * Given an index specification in the form of a string ensure that each
	 * column name is property quoted, stripping brackets and modifiers.
	 * This index may also be in the form of a "CREATE INDEX..." sql fragment
	 * 
	 * @param string $spec The input specification or query. E.g. 'unique (Column1, Column2)'
	 * @return string The properly quoted column list. E.g. '"Column1", "Column2"'
	 */
	protected static function quote_column_spec_string($spec) {
		$bits = self::explode_column_string($spec);
		return self::implode_column_list($bits);
	}

	/**
	 * Given an index spec determines the index type
	 * 
	 * @param type $spec
	 * @return string 
	 */
	protected static function determine_index_type($spec) {
		// check array spec
		if(is_array($spec) && isset($spec['type'])) {
			return $spec['type'];
		} elseif (!is_array($spec) && preg_match('/(?<type>\w+)\s*\(/', $spec, $matchType)) {
			return strtolower($matchType['type']);
		} else {
			return 'index';
		}
	}
	
	/**
	 * Converts an array or string index spec into a universally useful array
	 * 
	 * @param string|array $spec
	 * @return array The resulting spec array with the required fields name, type, and value
	 */
	protected static function parse_index_spec($name, $spec) {

		// Do minimal cleanup on any already parsed spec
		if(is_array($spec)) {
			$spec['value'] = self::quote_column_spec_string($spec['value']);
			return $spec;
		}

		// Nicely formatted spec!
		return array(
			'name' => $name,
			'value' => self::quote_column_spec_string($spec),
			'type' => self::determine_index_type($spec)
		);
	}

	// </editor-fold>
	
	// <editor-fold defaultstate="collapsed" desc="Database Field Generation">
	
	/**
	 * Generates the extra DB fields for a class (not including subclasses)
	 * 
	 * @param string $class
	 */
	public static function generate_extra_config($class) {
		
		// Generate $db for class
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
		
		// Generate $indexes for class
		$baseIndexes = self::base_indexes($class);
		$indexes = array();
		if($baseIndexes) foreach($baseIndexes as $baseIndex => $baseSpec) {
			if($baseSpec === 1 || $baseSpec === true) {
				if(isset($baseFields[$baseIndex])) {
					// Single field is translated, so add multiple indexes for each locale
					foreach(Fluent::locales() as $locale) {
						// Transform has_one relations into basic int fields to prevent interference with ORM
						$translatedName = Fluent::db_field_for_locale($baseIndex, $locale);
						$indexes[$translatedName] = $baseSpec;
					}
				}
			} else {
				// Check format of spec
				$baseSpec = self::parse_index_spec($baseIndex, $baseSpec);
				
				// Check if columns overlap with translated
				$columns = self::explode_column_string($baseSpec['value']);
				$translatedColumns = array_intersect(array_keys($baseFields), $columns);
				if($translatedColumns) {
					// Generate locale specific version of this index
					foreach(Fluent::locales() as $locale) {
						$newColumns = array();
						foreach($columns as $column) {
							$newColumns[] = isset($baseFields[$column])
								? Fluent::db_field_for_locale($column, $locale)
								: $column;
						}
						
						// Inject new columns and save
						$newSpec = array_merge($baseSpec, array(
							'name' => Fluent::db_field_for_locale($baseIndex, $locale),
							'value' => self::implode_column_list($newColumns)
						));
						$indexes[$newSpec['name']] = $newSpec;
					}
				}
			}
			
		}
		
		return array(
			'db' => $db,
			'indexes' => $indexes
		);
	}
	
	public static function get_extra_config($class, $extension, $args) {
		if(self::$disable_fluent_fields) return array();
		
		// Merge all config values for subclasses
		foreach (ClassInfo::subclassesFor($class) as $subClass) {
			$config = self::generate_extra_config($subClass);
			foreach($config as $name => $value) {
				Config::inst()->update($subClass, $name, $value);
			}
		}
		
		// Force all subclass DB caches to invalidate themselves since their db attribute is now expired
		DataObject::reset();
		
		return self::generate_extra_config($class);
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
			$data[] = $this->owner->LocaleInformation($locale);
		}
		return new ArrayList($data);
	}
	
	/**
	 * Determine the link to this object given the specified $locale.
	 * Returns null for DataObjects that do not have a 'Link' function.
	 * 
	 * @param string $locale
	 * @return string
	 */
	public function LocaleLink($locale) {
		
		// Skip dataobjects that do not have the Link method
		if(!$this->owner->hasMethod('Link')) return null;
		
		// Return locale root url if unable to view this item in this locale
		if($this->owner->hasMethod('canViewInLocale') && !$this->owner->canViewInLocale($locale)) {
			return $this->owner->BaseURLForLocale($locale);
		}
		
		// Mock locale and recalculate link
		$id = $this->owner->ID;
		$class = $this->owner->ClassName;
		return Fluent::with_locale($locale, function() use ($id, $class) {
			return DataObject::get($class)->byID($id)->Link();
		});
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
	 * Retrieves information about this object in the specified locale
	 * 
	 * @param string $locale The locale information to request, or null to use the default locale
	 * @return ArrayData Mapped list of locale properties
	 */
	public function LocaleInformation($locale = null) {
		
		// Check locale
		if(empty($locale)) $locale = Fluent::default_locale();
		
		// Check linking mode
		$linkingMode = 'link';
		if($this->owner->hasMethod('canViewInLocale') && !$this->owner->canViewInLocale($locale)) {
			$linkingMode = 'invalid';
		} elseif($locale === Fluent::current_locale()) {
			$linkingMode = 'current';
		}
		
		// Check link
		$link = $this->owner->LocaleLink($locale);
		
		// Store basic locale information
		$data = array(
			'Locale' => $locale,
			'LocaleRFC1766' => i18n::convert_rfc1766($locale),
			'Alias' => Fluent::alias($locale),
			'Title' => i18n::get_locale_name($locale),
			'Link' => $link,
			'AbsoluteLink' => $link ? Director::absoluteURL($link) : null,
			'LinkingMode' => $linkingMode
		);
		
		return new ArrayData($data);
	}
	
	/**
	 * Current locale code
	 * 
	 * @return string Locale code
	 */
	public function CurrentLocale() {
		return Fluent::current_locale();
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
	
	/**
	 * Generates a select fragment based on a field with a fallback
	 * 
	 * @param string $class Table/Class name
	 * @param string $select Column to select from
	 * @param string $fallback Column to fallback to if $select is empty
	 * @return string Select fragment
	 */
	protected function localiseSelect($class, $select, $fallback) {
		return "CASE
				WHEN (\"{$class}\".\"{$select}\" IS NOT NULL AND \"{$class}\".\"{$select}\" != '')
				THEN \"{$class}\".\"{$select}\"
				ELSE \"{$class}\".\"{$fallback}\" END";
	}

	public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		
		// Get locale and translation zone to use
		$defaultLocale = Fluent::default_locale();
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

			// Select visible field from translated fields (Title_fr_FR || Title => Title)
			$translatedField = Fluent::db_field_for_locale($field, $locale);
			$expression = $this->localiseSelect($class, $translatedField, $field);
			$query->selectField($expression, $alias);
			
			// If selecting in a non-default locale, then ensure we maintain the default locale value
			// (Title_en_NZ || Title => Title_en_NZ)
			if($defaultLocale !== $locale) {
				$defaultField = Fluent::db_field_for_locale($field, $defaultLocale);
				$defaultExpression = $this->localiseSelect($class, $defaultField, $field);
				$query->selectField($defaultExpression, $defaultField);
			}
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
		
		// Augment search if applicable
		if($adapter = Fluent::search_adapter()) {
			$adapter->augmentSearch($query, $dataQuery);
		}
	}

	public function augmentWrite(&$manipulation) {
		
		// Bypass augment write if requested
		if(!self::$_enable_write_augmentation) return;
		
		// Get locale and translation zone to use
		$locale = Fluent::current_locale();
		$defaultLocale = Fluent::default_locale();
							
		// Get all tables to translate fields for, and their respective field names
		$includedTables = $this->getTranslatedTables();
		
		// Iterate through each select clause, replacing each with the translated version
		foreach($manipulation as $class => $updates) {

			// If this table doesn't have translated fields then skip
			if(empty($includedTables[$class])) continue;
			
			foreach($includedTables[$class] as $field) {
				
				// Skip translated field if not updated in this request
				if(!isset($updates['fields'][$field])) continue;
					
				// Copy the updated value to the locale specific field
				// (Title => Title_fr_FR)
				$updateField = Fluent::db_field_for_locale($field, $locale);
				$updates['fields'][$updateField] = $updates['fields'][$field];

				// If not on the default locale, write the stored default field back to the main field
				// (if Title_en_NZ then Title_en_NZ => Title)
				if($locale !== $defaultLocale) {
					$defaultField = Fluent::db_field_for_locale($field, $defaultLocale);
					if(!empty($updates['fields'][$defaultField])) {
						$updates['fields'][$field] = $updates['fields'][$defaultField];
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
