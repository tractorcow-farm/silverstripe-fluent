<?php

/**
 * Provides migration from the Translatable module to the Fluent format
 * 
 * Don't forget to:
 * 1. Back up your DB
 * 2. Configure fluent
 * 3. dev/build
 * 4. Back up your DB again
 * 5. Log into the CMS
 *
 * @package fluent
 * @author Damian Mooyman <damian.mooyman@gmail.com>
 */
class ConvertTranslatableTask extends BuildTask {
	
	protected $title = "Convert Translatable > Fluent Task";

	protected $description = "Migrates site DB from Translatable DB format to Fluent.";
	
	/**
	 * Checks that fluent is configured correctly
	 * 
	 * @throws ConvertTranslatableException
	 */
	protected function checkInstalled() {
		// Assert that fluent is configured
		$locales = Fluent::locales();
		if(empty($locales)) {
			throw new ConvertTranslatableException("Please configure Fluent.locales prior to migrating from translatable");
		}
		$defaultLocale = Fluent::default_locale();
		if(empty($defaultLocale) || !in_array($defaultLocale, $locales)) {
			throw new ConvertTranslatableException("Please configure Fluent.default_locale prior to migrating from translatable");
		}
	}
	
	/**
	 * Do something inside a DB transaction
	 * 
	 * @param callable $callback
	 * @throws Exception
	 */
	protected function withTransaction($callback) {
		try {
			Debug::message('Beginning transaction', false);
			DB::getConn()->transactionStart();
			$callback($this);
			Debug::message('Comitting transaction', false);
			DB::getConn()->transactionEnd();
		} catch(Exception $ex) {
			Debug::message('Rolling back transaction', false);
			DB::getConn()->transactionRollback();
			throw $ex;
		}
	}
	
	protected $translatedFields = array();
	
	/**
	 * Get all database fields to translate
	 * 
	 * @param string $class Class name
	 * @return array List of translated fields
	 */
	public function getTranslatedFields($class) {
		if(isset($this->translatedFields[$class])) {
			return $this->translatedFields[$class];
		}
		$fields = array();
		$hierarchy = ClassInfo::ancestry($class);
		foreach($hierarchy as $class) {
			
			// Skip classes without tables
			if(!DataObject::has_own_table($class)) continue;
			
			// Check translated fields for this class
			$translatedFields = FluentExtension::translated_fields_for($class);
			if(empty($translatedFields)) continue;
			
			// Save fields
			$fields = array_merge($fields, array_keys($translatedFields));
		}
		$this->translatedFields[$class] = $fields;
		return $fields;
	}
	
	/**
	 * Gets all classes with FluentExtension
	 * 
	 * @return array of classes to migrate
	 */
	public function fluentClasses() {
		$classes = array();
		$dataClasses = ClassInfo::subclassesFor('DataObject');
		array_shift($dataClasses);
		foreach($dataClasses as $class) {
			$base = ClassInfo::baseDataClass($class);
			foreach(Object::get_extensions($base) as $extension) {
				if(is_a($extension, 'FluentExtension', true)) {
					$classes[] = $base;
					break;
				}
			}
		}
		return array_unique($classes);
	}
	
	public function run($request) {
		
		// Extend time limit
		set_time_limit(10000);
		
		$this->checkInstalled();
		$this->withTransaction(function($task) {
			Versioned::reading_stage('Stage');
			$classes = $task->fluentClasses();
			$tables = DB::tableList();
			foreach($classes as $class) {
				
				// Ensure that a translationgroup table exists for this class
				$groupTable = strtolower($class."_translationgroups");
				if(isset($tables[$groupTable])) {
					$groupTable = $tables[$groupTable];
				} else {
					Debug::message("Ignoring class without _translationgroups table $class", false);
					continue;
				}
				
				// Disable filter
				if($class::has_extension('FluentFilteredExtension')) {
					$class::remove_extension('FluentFilteredExtension');
				}
				
				// Select all instances of this class in the default locale
				$instances = DataObject::get($class, sprintf(
					'"Locale" = \'%s\'',
					Convert::raw2sql(Fluent::default_locale())
				));
				foreach($instances as $instance) {
					// Force lazy loading of fields
					$instanceID = $instance->ID;
					$translatedFields = $task->getTranslatedFields($instance->ClassName);
					Debug::message("Migrating item {$instance->ClassName} with ID {$instanceID}", false);
					
					// Select all translations for this
					$translatedItems = DataObject::get($class, sprintf(
						'"Locale" != \'%1$s\' AND "ID" IN (
							SELECT "OriginalID" FROM "%2$s" WHERE "TranslationGroupID" IN (
								SELECT "TranslationGroupID" FROM "%2$s" WHERE "OriginalID" = %3$d
							)
						)',
						Convert::raw2sql(Fluent::default_locale()),
						$groupTable,
						$instanceID
					));
					foreach($translatedItems as $translatedItem) {
						
						// Extract information for this locale
						$translatedValues = array_intersect_key(
							$translatedItem->toMap(),
							array_flip($translatedFields)
						);
						$locale = DB::query(sprintf(
							'SELECT "Locale" FROM "%s" WHERE "ID" = %d',
							$class,
							$translatedItem->ID
						))->value();
						
						// Unpublish and delete translated record
						if($translatedItem->hasMethod('doUnpublish')) {
							Debug::message("  --  Unpublishing $locale", false);
							if($translatedItem->doUnpublish() === false) {
								throw new ConvertTranslatableException("Failed to unpublish");
							}
						}
						Debug::message("  --  Removing old record $locale", false);
						$translatedItem->delete();
						
						// Load this information into
						Fluent::with_locale($locale, function() use ($class, $instanceID, $translatedValues, $locale) {
							Debug::message("  --  Migrating to locale $locale", false);
							$item = DataObject::get_by_id($class, $instanceID, false);
							foreach($translatedValues as $field => $value) {
								$item->$field = $value;
							}
							$item->write();
						});
					}
					
					// Publish main item
					$item = DataObject::get_by_id($class, $instanceID, false);
					if($item->hasMethod('doPublish')) {
						Debug::message("  --  Publishing", false);
						if($item->doPublish() === false) {
							throw new ConvertTranslatableException("Failed to publish");
						}
					}
				}
			}
		});
	}	
}
