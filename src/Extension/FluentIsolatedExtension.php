<?php

namespace TractorCow\Fluent\Extension;

use LogicException;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Represents an object that can only exist in a single locale
 *
 * Note: You cannot use this extension on any object with the other fluent extensions
 *
 * @property int $LocaleID
 * @method Locale Locale()
 *
 * @extends DataExtension<DataObject&static>
 */
class FluentIsolatedExtension extends DataExtension
{
    private static $has_one = [
        'Locale' => Locale::class,
    ];

    public function onBeforeWrite()
    {
        if (empty($this->owner->LocaleID)) {
            $locale = Locale::getCurrentLocale();
            if ($locale) {
                $this->owner->LocaleID = $locale->ID;
            }
        }
    }

    /**
     * Amend freshly created DataQuery objects with the current locale and frontend status
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentDataQueryCreation(SQLSelect $query, DataQuery $dataQuery)
    {
        $state = FluentState::singleton();
        $dataQuery
            ->setQueryParam('Fluent.Locale', $state->getLocale())
            ->setQueryParam('Fluent.IsFrontend', $state->getIsFrontend());
    }

    /**
     * Safety checks for config are done on dev/build
     *
     * @throws LogicException
     */
    public function augmentDatabase()
    {
        // Safety check: This extension cannot be added with fluent or filtered extensions
        if ($this->owner->hasExtension(FluentFilteredExtension::class)
            || $this->owner->hasExtension(FluentExtension::class)
        ) {
            throw new LogicException(
                "FluentIsolatedExtension cannot be used with any other fluent extensions. Check "
                . get_class($this->owner)
            );
        }
    }

    public function requireDefaultRecords()
    {
        // Migrate records that used to be FluentFilteredExtension
        $this->migrateFromFilteredExtension();
    }

    /**
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $locale = $this->getDataQueryLocale($dataQuery);
        if (!$locale) {
            return;
        }

        // Check if we should filter in CMS (default to true)
        if (!$this->owner->config()->get('apply_isolated_locales_to_admin')
            && !FluentState::singleton()->getIsFrontend()
        ) {
            return;
        }

        // skip filter by ID
        if (!$this->owner->config()->get('apply_isolated_locales_to_byid')
            && !FluentState::singleton()->getIsFrontend()
            && $this->getIsIDFiltered($query)
        ) {
            return;
        }

        // Apply filter
        $table = $this->owner->baseTable();
        $query->addWhere([
            "\"{$table}\".\"LocaleID\" = ?" => [$locale->ID],
        ]);
    }

    /**
     * Get current locale from given dataquery
     *
     * @param DataQuery $dataQuery
     * @return Locale|null
     */
    protected function getDataQueryLocale(DataQuery $dataQuery = null)
    {
        if (!$dataQuery) {
            return null;
        }

        $localeCode = $dataQuery->getQueryParam('Fluent.Locale');
        if ($localeCode) {
            return Locale::getByLocale($localeCode);
        }

        return Locale::getCurrentLocale();
    }

    /**
     * Soft-migration for records that used to be FluentFilteredExtension
     * Set the locale for records with missing LocaleID to the first
     * locale they had selected
     */
    protected function migrateFromFilteredExtension(): void
    {
        // Soft-migration for records that used to be FluentFilteredExtension
        // Set the locale for records with missing LocaleID to the first
        // locale they had selected
        $baseClass = $this->owner->baseClass();
        if ($baseClass !== get_class($this->owner)) {
            return;
        }

        // Check if this record used to have the filtered extension
        $table = $this->owner->baseTable();
        $filteredTable = "{$table}_FilteredLocales";
        $tables = DB::table_list();
        if (!array_key_exists(strtolower($filteredTable), $tables)) {
            return;
        }

        // Check if the LocaleID field exists
        if (!DB::get_schema()->hasField($table, 'LocaleID')) {
            return;
        }

        // To prevent cross-sql errors, this code is a bit inefficient
        $sql = <<<SQL
SELECT "{$table}"."ID" AS "ID", "{$filteredTable}"."Fluent_LocaleID" AS "LocaleID"
FROM "{$table}"
INNER JOIN "{$filteredTable}"
ON "{$table}"."ID" = "{$filteredTable}"."{$table}ID"
WHERE "{$table}"."LocaleID" = 0
SQL;
        $records = DB::query($sql);
        $count = $records->numRecords();
        if (!$count) {
            return;
        }

        foreach ($records as $row) {
            DB::prepared_query(
                "UPDATE \"{$table}\" SET \"LocaleID\" = ? WHERE \"ID\" = ?",
                [
                    $row['LocaleID'],
                    $row['ID'],
                ]
            );
        }

        DB::alteration_message("Migrated {$count} records to FluentIsolatedExtension", 'repaired');
    }

    /**
     * Determine if this record is being filtered by ID
     *
     * @param SQLSelect $query
     * @return bool
     */
    protected function getIsIDFiltered(SQLSelect $query): bool
    {
        // Use default silverstripe id filtering detection
        if ($query->filtersOnID() || $query->filtersOnFK()) {
            return true;
        }

        // Check if ID is joined (inner only)
        $table = $this->owner->baseTable();
        foreach ($query->getJoins($parameters) as $join) {
            // find inner joins on the primary key (e.g. used in many_many relations)
            $idField = DataObject::getSchema()->sqlColumnForField($this->owner, 'ID');
            if (stristr($join, 'INNER JOIN') && stristr($join, $idField)) {
                return true;
            }
        }
        return false;
    }
}
