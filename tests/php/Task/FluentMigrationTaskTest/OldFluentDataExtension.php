<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Versioned\Versioned;

class OldFluentDataExtension extends Extension
{

    /**
     * cache for fluent's old translated fields
     * @var array
     */
    private $oldData = [];


    public function onBeforeWrite()
    {
        $this->cacheOldDataFromDB();
    }

    /**
     * Write old data to the base table
     */
    public function onAfterWrite()
    {
        $ancestry = array_reverse(ClassInfo::ancestry($this->owner->ClassName));
        $schema = $this->owner->getSchema();

        $isVersioned = $this->owner->hasExtension(Versioned::class) && $this->owner->hasStages();

        foreach ($ancestry as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }
            $table = $schema->tableName($class);
            $update = SQLUpdate::create()
                ->setTable($table)
                ->setWhere('ID = ' . $this->owner->ID);

            if ($isVersioned) {
                $versionUpdate = SQLUpdate::create()
                    ->setTable($table . '_Versions')
                    ->setWhere('RecordID = ' . $this->owner->ID . ' AND Version = ' . $this->owner->Version);
            }

            foreach ($class::config()->get('old_fluent_fields', Config::UNINHERITED) as $field) {
                $update->assign($field, $this->getOldDataField($table, $field)); //value is set from fixtures in $this->record
                $update->execute();

                if ($isVersioned && $this->owner->Version) {
                    $versionUpdate->assign($field, $this->getOldDataField($table, $field));
                    $versionUpdate->execute();
                }
            }
        }
    }

    public function onBeforeVersionedPublish()
    {

        $this->cacheOldDataFromDB();
    }

    public function cacheOldDataFromDB()
    {
        if (!$this->owner->exists()) {
            return;
        }
        $id = $this->owner->ID;
        //grab old Data from DB and cache it

        $ancestry = array_reverse(ClassInfo::ancestry($this->owner->ClassName));
        $schema = $this->owner->getSchema();
        foreach ($ancestry as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }

            $oldTranslatedData = SQLSelect::create()
                ->setFrom($schema->tableName($class))
                ->setWhere('ID = ' . $this->owner->ID)
                ->firstRow()
                ->execute()
                ->record();
            $this->oldData[$id][$schema->tableName($class)] = $oldTranslatedData;
        }
    }


    public function onAfterVersionedPublish()
    {
        if (!$this->owner->exists()) {
            return;
        }

        if (!$this->owner->hasExtension(Versioned::class) && !$this->owner->hasStages()) {
            //how did we get here?
            return;
        }


        $ancestry = array_reverse(ClassInfo::ancestry($this->owner->ClassName));
        $schema = $this->owner->getSchema();
        foreach ($ancestry as $class) {
            if (!$schema->classHasTable($class)) {
                continue;
            }
            $stageTable = $schema->tableName($class);
            $table = $schema->tableName($class) . '_Live';
            $update = SQLUpdate::create()
                ->setTable($table)
                ->setWhere('ID = ' . $this->owner->ID);


            foreach ($class::config()->get('old_fluent_fields', Config::UNINHERITED) as $field) {
                $update->assign($field,
                    $this->getOldDataField($stageTable, $field)); //value is set from fixtures in $this->record
                $update->execute();

            }
        }
    }

    public function getOldDataField($table, $field)
    {
        $id = $this->owner->ID;
        if ($id
            && array_key_exists($id, $this->oldData)
            && array_key_exists($table, $this->oldData[$id])
            && array_key_exists($field, $this->oldData[$id][$table])) {
            return $this->oldData[$id][$table][$field];
        }
        return $this->owner->getField($field);
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

            if ($this->owner->hasExtension(Versioned::class) && $this->owner->hasStages()) {
                $schemaManager->requireField($table_name . '_Live', $field, 'VARCHAR(255) NULL DEFAULT NULL ');
                $schemaManager->requireField($table_name . '_Versions', $field, 'VARCHAR(255) NULL DEFAULT NULL ');
            }

        }
    }
}
