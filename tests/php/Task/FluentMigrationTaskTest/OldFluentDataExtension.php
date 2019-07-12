<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLUpdate;

class OldFluentDataExtension extends Extension
{
    public function onAfterWrite()
    {
        $ancestry = array_reverse(ClassInfo::ancestry($this->owner->ClassName));
        $schema = $this->owner->getSchema();

        foreach ($ancestry as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }
            $table = $schema->tableName($class);
            $update = SQLUpdate::create()
                ->setTable($table)
                ->setWhere('ID = ' . $this->owner->ID);

            foreach ($class::config()->get('old_fluent_fields', Config::UNINHERITED) as $field) {
                $update->assign($field, $this->owner->getField($field)); //value is set from fixtures in $this->record
                $update->execute();
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

        foreach ($this->owner->config()->get('old_fluent_fields') as $field) {
            $schemaManager->requireField($table_name, $field, 'VARCHAR(255) NULL DEFAULT NULL ');
        }
    }
}