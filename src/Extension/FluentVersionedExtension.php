<?php

namespace TractorCow\Fluent\Extension;

use InvalidArgumentException;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

/**
 * Extension for versioned localised objects
 */
class FluentVersionedExtension extends FluentExtension
{
    protected function augmentDatabaseDontRequire($localisedTable)
    {
        DB::dont_require_table($localisedTable);
        DB::dont_require_table($localisedTable.'_Live');
        DB::dont_require_table($localisedTable.'_Versions');
    }

    protected function augmentDatabaseRequireTable($localisedTable, $fields, $indexes)
    {
        DB::require_table($localisedTable, $fields, $indexes, false);

        // _Live record
        DB::require_table($localisedTable . '_Live', $fields, $indexes, false);

        // _Versions has extra Version column
        $versionsFields = array_merge(
            ['Version' => 'Int'],
            $fields
        );

        // Adjust unique index to include Version column as well
        $versionsIndexes = array_merge(
            $indexes,
            [
                'Fluent_Record' => [
                    'type' => 'unique',
                    'columns' => [
                        'RecordID',
                        'Locale',
                        'Version',
                    ],
                ],
            ]
        );
        DB::require_table($localisedTable . '_Versions', $versionsFields, $versionsIndexes, false);
    }

    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $locale = $this->getDataQueryLocale($dataQuery);
        if (!$locale) {
            return;
        }

        // Rewrite query un-versioned
        parent::augmentSQL($query, $dataQuery);

        // Rewrite based on versioned rules
        if (!$dataQuery->getQueryParam('Versioned.mode')) {
            return;
        }

        $baseTable = $this->owner->baseTable();
        $tables = $this->getLocalisedTables();
        $versionedMode = $dataQuery->getQueryParam('Versioned.mode');
        switch ($versionedMode) {
            // Reading a specific stage (Stage or Live)
            case 'stage':
            case 'stage_unique':
                // Check if we need to rewrite this table
                $stage = $dataQuery->getQueryParam('Versioned.stage');
                if ($stage === Versioned::DRAFT) {
                    return;
                }
                // Rename all localised tables (note: alias remains unchanged)
                foreach ($tables as $table => $fields) {
                    $localisedTable = $this->getLocalisedTable($table);
                    $stageTable = $localisedTable . '_Live';
                    $query->renameTable($localisedTable, $stageTable);
                }
                break;
            // Return all version instances
            case 'archive':
            case 'all_versions':
            case 'latest_versions':
            case 'version':
                // Rewrite all joined tables
                foreach ($tables as $table => $fields) {
                    // Rename to _Versions suffixed versions
                    $localisedTable = $this->getLocalisedTable($table);
                    $query->renameTable($localisedTable, $localisedTable . '_Versions');

                    // Update all joins to include Version as well as Locale / Record
                    foreach ($locale->getChain() as $joinLocale) {
                        $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
                        $query->setJoinFilter(
                            $joinAlias,
                            "\"{$baseTable}_Versions\".\"RecordID\" = \"{$joinAlias}\".\"RecordID\" "
                            . "AND \"{$joinAlias}\".\"Locale\" = ? "
                            . "AND \"{$joinAlias}\".\"Version\" = \"{$baseTable}_Versions\".\"Version\""
                        );
                    }
                }
                break;
            default:
                throw new InvalidArgumentException("Bad value for query parameter Versioned.mode: {$versionedMode}");
        }
    }

    public function augmentWrite(&$manipulation)
    {
        parent::augmentWrite($manipulation);

        var_dump($manipulation);

        return $manipulation;
    }
}
