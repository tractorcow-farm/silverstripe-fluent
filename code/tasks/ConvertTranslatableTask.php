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
class ConvertTranslatableTask extends BuildTask
{
    protected $title = "Convert Translatable > Fluent Task";

    protected $description = "Migrates site DB from Translatable DB format to Fluent.";

    /**
     * Checks that fluent is configured correctly
     *
     * @throws ConvertTranslatableException
     */
    protected function checkInstalled()
    {
        // Assert that fluent is configured
        $locales = Fluent::locales();
        if (empty($locales)) {
            throw new ConvertTranslatableException("Please configure Fluent.locales prior to migrating from translatable");
        }
        $defaultLocale = Fluent::default_locale();
        if (empty($defaultLocale) || !in_array($defaultLocale, $locales)) {
            throw new ConvertTranslatableException("Please configure Fluent.default_locale prior to migrating from translatable");
        }
    }

    /**
     * Do something inside a DB transaction
     *
     * @param callable $callback
     * @throws Exception
     */
    protected function withTransaction($callback)
    {
        try {
            Debug::message('Beginning transaction', false);
            DB::getConn()->transactionStart();
            $callback($this);
            Debug::message('Comitting transaction', false);
            DB::getConn()->transactionEnd();
        } catch (Exception $ex) {
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
    public function getTranslatedFields($class)
    {
        if (isset($this->translatedFields[$class])) {
            return $this->translatedFields[$class];
        }
        $fields = array();
        $hierarchy = ClassInfo::ancestry($class);
        foreach ($hierarchy as $class) {

            // Skip classes without tables
            if (!DataObject::has_own_table($class)) {
                continue;
            }

            // Check translated fields for this class
            $translatedFields = FluentExtension::translated_fields_for($class);
            if (empty($translatedFields)) {
                continue;
            }

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
    public function fluentClasses()
    {
        $classes = array();
        $dataClasses = ClassInfo::subclassesFor('DataObject');
        array_shift($dataClasses);
        foreach ($dataClasses as $class) {
            $base = ClassInfo::baseDataClass($class);
            foreach (Object::get_extensions($base) as $extension) {
                if (is_a($extension, 'FluentExtension', true)) {
                    $classes[] = $base;
                    break;
                }
            }
        }
        return array_unique($classes);
    }

    public function run($request)
    {

        // Extend time limit
        set_time_limit(100000);

        // we may need some proivileges for this to work
        // without this, running under sake is a problem
        // maybe sake could take care of it ...
        Security::findAnAdministrator()->login();


        $this->checkInstalled();
        $this->withTransaction(function ($task) {
            Versioned::reading_stage('Stage');
            $classes = $task->fluentClasses();
            $tables = DB::tableList();
            $deleteQueue = array();
            foreach ($classes as $class) {

                // Ensure that a translationgroup table exists for this class
                $groupTable = strtolower($class."_translationgroups");
                if (isset($tables[$groupTable])) {
                    $groupTable = $tables[$groupTable];
                } else {
                    Debug::message("Ignoring class without _translationgroups table $class", false);
                    continue;
                }

                // Disable filter
                if ($class::has_extension('FluentFilteredExtension')) {
                    $class::remove_extension('FluentFilteredExtension');
                }

                // Select all instances of this class in the default locale
                $instances = DataObject::get($class, sprintf(
                    '"Locale" = \'%s\'',
                    Convert::raw2sql(Fluent::default_locale())
                ));
                foreach ($instances as $instance) {
                    $isPublished = false;
                    if ($instance->hasMethod('isPublished')) {
                        $isPublished = $instance->isPublished();
                    }

                    if ($instance->ObsoleteClassName) {
                        Debug::message("Skipping {$instance->ClassName} with ID {$instanceID} because it from an obsolete class", false);
                        continue;
                    }

                    $instanceID = $instance->ID;

                    $translatedFields = $task->getTranslatedFields($instance->ClassName);
                    Debug::message("Updating {$instance->ClassName} {$instance->MenuTitle} ({$instanceID})", false);
                    $changed = false;

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

                    foreach ($translatedItems as $translatedItem) {
                        $locale = DB::query(sprintf(
                            'SELECT "Locale" FROM "%s" WHERE "ID" = %d',
                            $class,
                            $translatedItem->ID
                        ))->value();

                        // since we are going to delete the stuff
                        // anyway, no need bothering validating it
                        DataObject::config()->validation_enabled = false;

                        // Unpublish and delete translated record
                        if ($translatedItem->hasMethod('doUnpublish')) {
                            Debug::message("  --  Unpublishing $locale", false);
                            if ($translatedItem->doUnpublish() === false) {
                                throw new ConvertTranslatableException("Failed to unpublish");
                            }
                        }
                        Debug::message("  --  Adding {$translatedItem->ID} ($locale)", false);
                        foreach ($translatedFields as $field) {
                            $trField = Fluent::db_field_for_locale($field, $locale);
                            if ($translatedItem->$field) {
                                Debug::message("     --  Adding $trField", false);
                                $instance->$trField = $translatedItem->$field;
                                $changed = true;
                            }
                        }
                        // for some reason, deleting items here has disruptive effects
                        // as too much stuff gets removed, so lets wait with this until the end of the migration
                        $deleteQueue[] = $translatedItem;
                    }
                    if ($changed) {
                        if (!$isPublished) {
                            $instance->write();
                        } elseif ($instance->doPublish() === false) {
                            Debug::message("  --  Publishing FAILED", false);
                            throw new ConvertTranslatableException("Failed to publish");
                        } else {
                            Debug::message("  --  Published", false);
                        }
                    }
                }
            }
            foreach ($deleteQueue as $delItem) {
                Debug::message("  --  Removing {$delItem->ID}", false);
                $delItem->delete();
            }
        });
    }
}
