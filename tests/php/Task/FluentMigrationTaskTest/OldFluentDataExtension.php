<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Versioned\Versioned;

class OldFluentDataExtension extends Extension
{
    /**
     * Write old data to the base table
     */
    public function onAfterWrite()
    {
        $ancestry = array_reverse(ClassInfo::ancestry($this->owner->ClassName));
        $schema = $this->owner->getSchema();

        $isVersioned = $this->owner->hasExtension(Versioned::class) &&$this->owner->hasStages();

        foreach ($ancestry as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }
            $table = $schema->tableName($class);
            $update = SQLUpdate::create()
                ->setTable($table)
                ->setWhere('ID = ' . $this->owner->ID);

            if($isVersioned) {
                $versionUpdate = SQLUpdate::create()
                    ->setTable($table . '_Versions')
                    ->setWhere('RecordID = ' . $this->owner->ID . ' AND Version = ' . $this->owner->Version);
            }

            foreach ($class::config()->get('old_fluent_fields', Config::UNINHERITED) as $field) {
                $update->assign($field, $this->owner->getField($field)); //value is set from fixtures in $this->record
                $update->execute();

                if ($isVersioned) {
                    $versionUpdate->assign($field, $this->owner->getField($field));
                    $versionUpdate->execute();
                }
            }
        }
    }


    /**
     * Helper to get the old *_translationgroups table and the Locale field created
     */
    public function augmentDatabase()
    {
        $schemaManager = DB::get_schema();
        $table_name = $this->owner->getSchema()->tableName($this->owner->ClassName);

        $oldFields = (array)$this->owner->config()->get('old_fluent_fields', Config::UNINHERITED);

        foreach ($oldFields as $field) {
            $schemaManager->requireField($table_name, $field, 'VARCHAR(255) NULL DEFAULT NULL ');

            if ($this->owner->hasExtension(Versioned::class) &&$this->owner->hasStages()) {
                $schemaManager->requireField($table_name . '_Live', $field, 'VARCHAR(255) NULL DEFAULT NULL ');
                $schemaManager->requireField($table_name. '_Versions', $field , 'VARCHAR(255) NULL DEFAULT NULL ');
            }

        }
    }
}