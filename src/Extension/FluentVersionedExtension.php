<?php

namespace TractorCow\Fluent\Extension;

use InvalidArgumentException;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Extension for versioned localised objects
 *
 * Important: If adding this to a custom object, this extension must be added AFTER the versioned extension.
 * Use yaml `after` to enforce this
 */
class FluentVersionedExtension extends FluentExtension
{
    /**
     * Live table suffix
     */
    const SUFFIX_LIVE = 'Live';

    /**
     * Versions table suffix
     */
    const SUFFIX_VERSIONS = 'Versions';

    /**
     * Default version table fields. _Versions has extra Version column.
     *
     * @var array
     */
    protected $defaultVersionsFields = [
        'Version' => 'Int',
    ];

    /**
     * Default version table indexes, including unique index to include Version column.
     *
     * @var array
     */
    protected $defaultVersionsIndexes = [
        'Fluent_Record' => [
            'type' => 'unique',
            'columns' => [
                'RecordID',
                'Locale',
                'Version',
            ],
        ],
    ];

    protected function augmentDatabaseDontRequire($localisedTable)
    {
        DB::dont_require_table($localisedTable);
        DB::dont_require_table($localisedTable . '_' . self::SUFFIX_LIVE);
        DB::dont_require_table($localisedTable . '_' . self::SUFFIX_VERSIONS);
    }

    protected function augmentDatabaseRequireTable($localisedTable, $fields, $indexes)
    {
        DB::require_table($localisedTable, $fields, $indexes, false);

        // _Live record
        DB::require_table($localisedTable . '_' . self::SUFFIX_LIVE, $fields, $indexes, false);

        // Merge fields and indexes with Fluent defaults
        $versionsFields = array_merge($this->defaultVersionsFields, $fields);
        $versionsIndexes = array_merge($indexes, $this->defaultVersionsIndexes);

        DB::require_table($localisedTable . '_' . self::SUFFIX_VERSIONS, $versionsFields, $versionsIndexes, false);
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException if an invalid versioned mode is provided
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        /** @var Locale|null $locale */
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

        $tables = $this->getLocalisedTables();
        $versionedMode = $dataQuery->getQueryParam('Versioned.mode');
        switch ($versionedMode) {
            // Reading a specific stage (Stage or Live)
            case 'stage':
            case 'stage_unique':
                // Rename all localised tables (note: alias remains unchanged). This is only done outside of draft.
                $stage = $dataQuery->getQueryParam('Versioned.stage');
                if ($stage !== Versioned::DRAFT) {
                    $this->renameLocalisedTables($query, $tables);
                }
                break;
            // Return all version instances
            case 'archive':
            case 'all_versions':
            case 'latest_versions':
            case 'version':
                $this->rewriteVersionedTables($query, $tables, $locale);
                break;
            default:
                throw new InvalidArgumentException("Bad value for query parameter Versioned.mode: {$versionedMode}");
        }
    }

    /**
     * Rewrite all joined tables
     *
     * @param SQLSelect $query
     * @param array $tables
     * @param Locale $locale
     */
    protected function rewriteVersionedTables(SQLSelect $query, array $tables, Locale $locale)
    {
        foreach ($tables as $tableName => $fields) {
            // Rename to _Versions suffixed versions
            $localisedTable = $this->getLocalisedTable($tableName);
            $query->renameTable($localisedTable, $localisedTable . '_' . self::SUFFIX_VERSIONS);

            // Add the chain of locale fallbacks
            $this->addLocaleFallbackChain($query, $tableName, $locale);
        }
    }

    /**
     * Update all joins to include Version as well as Locale / Record
     *
     * @param SQLSelect $query
     * @param string $tableName
     * @param Locale $locale
     */
    protected function addLocaleFallbackChain(SQLSelect $query, $tableName, Locale $locale)
    {
        $baseTable = $this->owner->baseTable();

        foreach ($locale->getChain() as $joinLocale) {
            /** @var Locale $joinLocale */
            $joinAlias = $this->getLocalisedTable($tableName, $joinLocale->Locale);

            $query->setJoinFilter(
                $joinAlias,
                "\"{$baseTable}_Versions\".\"RecordID\" = \"{$joinAlias}\".\"RecordID\" "
                . "AND \"{$joinAlias}\".\"Locale\" = ? "
                . "AND \"{$joinAlias}\".\"Version\" = \"{$baseTable}_" . self::SUFFIX_VERSIONS . "\".\"Version\""
            );
        }
    }

    /**
     * Rename all localised tables to the "live" equivalent name (note: alias remains unchanged)
     *
     * @param SQLSelect $query
     * @param array $tables
     */
    protected function renameLocalisedTables(SQLSelect $query, array $tables)
    {
        foreach ($tables as $table => $fields) {
            $localisedTable = $this->getLocalisedTable($table);
            $query->renameTable($localisedTable, $localisedTable . '_' . self::SUFFIX_LIVE);
        }
    }

    /**
     * Apply versioning to write
     *
     * @param array $manipulation
     */
    public function augmentWrite(&$manipulation)
    {
        parent::augmentWrite($manipulation);

        // Only rewrite if the locale is valid
        $locale = $this->getRecordLocale();
        if (!$locale) {
            return;
        }

        // Get all tables to translate fields for, and their respective field names
        $includedTables = $this->getLocalisedTables();
        foreach ($includedTables as $table => $localisedFields) {
            // Localise both _Versions and _Live writes
            foreach ([self::SUFFIX_LIVE, self::SUFFIX_VERSIONS] as $suffix) {
                $versionedTable = $table . '_' . $suffix;
                $localisedTable = $this->getLocalisedTable($table) . '_' . $suffix;

                // Add extra case for "Version" column when localising Versions
                $localisedVersionFields = $localisedFields;
                if ($suffix === self::SUFFIX_VERSIONS) {
                    $localisedVersionFields = array_merge(
                        $localisedVersionFields,
                        array_keys($this->defaultVersionsFields)
                    );
                }

                // Rewrite manipulation
                $this->localiseManipulationTable(
                    $manipulation,
                    $versionedTable,
                    $localisedTable,
                    $localisedVersionFields,
                    $locale
                );
            }
        }
    }

    /**
     * Decorate table to delete with _Live suffix as necessary
     *
     * @param string $tableName
     * @param string $locale
     * @return string
     */
    protected function getDeleteTableTarget($tableName, $locale = '')
    {
        // Rewrite to _Live when deleting from live / unpublishing
        $table = parent::getDeleteTableTarget($tableName, $locale);
        if (Versioned::get_stage() === Versioned::LIVE) {
            $table .= '_' . self::SUFFIX_LIVE;
        }
        return $table;
    }

    /**
     * @param string $locale
     * @return bool
     */
    public function isDraftedInLocale($locale = '')
    {
        $localisedTable = $this->getLocalisedTable($this->owner->baseTable());

        return $this->localeRecordExistsInTable($localisedTable, $locale);
    }

    /**
     * @param string $locale
     * @return bool
     */
    public function isPublishedInLocale($locale = '')
    {
        $localisedTable = $this->getLocalisedTable($this->owner->baseTable()) . '_' . self::SUFFIX_LIVE;

        return $this->localeRecordExistsInTable($localisedTable, $locale);
    }

    /**
     * Check to see whether or not a record exists for a specific Locale in a specific table.
     *
     * @param $table
     * @param string $locale
     * @return bool
     */
    protected function localeRecordExistsInTable($table, $locale = '')
    {
        if ($locale === '') {
            $locale = FluentState::singleton()->getLocale();

            // Potentially no Locales have been created in the system yet.
            if (!$locale) {
                return false;
            }
        }

        $query = new SQLSelect();
        $query->selectField('ID');
        $query->addFrom($table);
        $query->addWhere([
            'RecordID' => $this->owner->ID,
            'Locale' => $locale,
        ]);
        $query->firstRow();

        return $query->execute()->value() !== null;
    }
}
