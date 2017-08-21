<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Basic fluent extension
 */
class FluentExtension extends DataExtension
{
    /**
     * The table suffix that will be applied to create localisation tables
     *
     * @var string
     */
    const SUFFIX = 'Localised';

    /**
     * DB fields to be used added in when creating a localised version of the owner's table
     *
     * @config
     * @var array
     */
    private static $db_for_localised_table = [
        'ID' => 'PrimaryKey',
        'RecordID' => 'Int',
        'Locale' => 'Varchar(10)',
    ];

    /**
     * Indexes to create on a localised version of the owner's table
     *
     * @config
     * @var array
     */
    private static $indexes_for_localised_table = [
        'Fluent_Record' => [
            'type' => 'unique',
            'columns' => [
                'RecordID',
                'Locale',
            ],
        ],
    ];

    /**
     * Filter whitelist of fields to localise
     *
     * @config
     * @var array
     */
    private static $field_include = [];

    /**
     * Filter blacklist of fields to localise
     *
     * @config
     * @var array
     */
    private static $field_exclude = [
        'ID',
        'ClassName',
        'Theme',
        'Priority',
    ];

    /**
     * Filter whitelist of field types to localise
     *
     * @config
     * @var
     */
    private static $data_include = [
        'Text',
        'Varchar',
        'HTMLText',
        'HTMLVarchar',
    ];

    /**
     * Filter blacklist of field types to localise
     *
     * @config
     * @var array
     */
    private static $data_exclude = [];

    /**
     * Cache of localised fields for this model
     */
    protected $localisedFields = [];

    /**
     * Get list of fields that are localised
     *
     * @param string $class Class to get fields for (if parent)
     * @return array
     */
    public function getLocalisedFields($class = null)
    {
        if (!$class) {
            $class = get_class($this->owner);
        }
        if (isset($this->localisedFields[$class])) {
            return $this->localisedFields[$class];
        }

        // List of DB fields
        $fields = DataObject::getSchema()->databaseFields($class, false);
        $filter = Config::inst()->get($class, 'translate', Config::UNINHERITED);
        if ($filter === 'none' || empty($fields)) {
            return $this->localisedFields[$class] = [];
        }

        // Data and field filters
        $fieldsInclude = Config::inst()->get($class, 'field_include');
        $fieldsExclude = Config::inst()->get($class, 'field_exclude');
        $dataInclude = Config::inst()->get($class, 'data_include');
        $dataExclude = Config::inst()->get($class, 'data_exclude');

        // filter out DB
        foreach ($fields as $field => $type) {
            // If given an explicit field name filter, then remove non-presented fields
            if ($filter) {
                if (!in_array($field, $filter)) {
                    unset($fields[$field]);
                }
                continue;
            }

            // Without a name filter then check against each filter type
            if (($fieldsInclude && !$this->anyMatch($field, $fieldsInclude))
                || ($fieldsExclude && $this->anyMatch($field, $fieldsExclude))
                || ($dataInclude && !$this->anyMatch($type, $dataInclude))
                || ($dataExclude && $this->anyMatch($type, $dataExclude))
            ) {
                unset($fields[$field]);
            }
        }

        return $this->localisedFields[$class] = $fields;
    }

    /**
     * Get all database tables in the class ancestry and their respective
     * translatable fields
     *
     * @return array
     */
    protected function getLocalisedTables()
    {
        $includedTables = array();
        $baseClass = $this->owner->baseClass();
        foreach ($this->owner->getClassAncestry() as $class) {
            // Skip classes without tables
            if (!DataObject::getSchema()->classHasTable($class)) {
                continue;
            }

            // Check translated fields for this class (except base table, which is always scaffolded)
            $translatedFields = $this->getLocalisedFields($class);
            if (empty($translatedFields) && $class !== $baseClass) {
                continue;
            }

            // Mark this table as translatable
            $table = DataObject::getSchema()->tableName($class);
            $includedTables[$table] = array_keys($translatedFields);
        }
        return $includedTables;
    }

    /**
     * Helper function to check if the value given is present in any of the patterns.
     * This function is case sensitive by default.
     *
     * @param string $value A string value to check against, potentially with parameters (E.g. 'Varchar(1023)')
     * @param array $patterns A list of strings, some of which may be regular expressions
     * @return bool True if this $value is present in any of the $patterns
     */
    protected function anyMatch($value, $patterns)
    {
        // Test both explicit value, as well as the value stripped of any trailing parameters
        $valueBase = preg_replace('/\(.*/', '', $value);
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '/') === 0) {
                // Assume value prefaced with '/' are regexp
                if (preg_match($pattern, $value) || preg_match($pattern, $valueBase)) {
                    return true;
                }
            } else {
                // Assume simple string comparison otherwise
                if ($pattern === $value || $pattern === $valueBase) {
                    return true;
                }
            }
        }
        return false;
    }

    public function augmentDatabase()
    {
        // Build _Localisation table
        $class = get_class($this->owner);
        $baseClass = $this->owner->baseClass();
        $schema = DataObject::getSchema();

        // Don't require table if no fields and not base class
        $localisedFields = $this->getLocalisedFields($class);
        $localisedTable = $this->getLocalisedTable($schema->tableName($class));
        if (empty($localisedFields) && $class !== $baseClass) {
            DB::dont_require_table($localisedTable);
            return;
        }

        // Merge fields and indexes
        $fields = array_merge(
            $this->owner->config()->get('db_for_localised_table'),
            $localisedFields
        );
        $indexes = $this->owner->config()->get('indexes_for_localised_table');
        DB::require_table($localisedTable, $fields, $indexes, false);
    }

    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $localeCode = $dataQuery->getQueryParam('Fluent.Locale') ?: FluentState::singleton()->getLocale();
        if (!$localeCode) {
            return;
        }

        // Only rewrite if the locale is valid
        $locale = Locale::getByLocale($localeCode);
        if (!$locale) {
            return;
        }

        // Select locale as literal
        $query->selectField(Convert::raw2sql($locale->Locale, true), 'Locale');

        // Join all tables on the given locale code
        $tables = $this->getLocalisedTables();
        foreach ($tables as $table => $fields) {
            // Join all items in ancestory
            $joinLocale = $locale;
            while ($joinLocale) {
                $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
                $query->addLeftJoin(
                    $this->getLocalisedTable($table),
                    "\"{$table}\".\"ID\" = \"{$joinAlias}\".\"RecordID\" AND \"{$joinAlias}\".\"Locale\" = ?",
                    $joinAlias,
                    20,
                    [ $joinLocale->Locale ]
                );
                // Join next parent
                $joinLocale = $joinLocale->getParent();
            }
        }

        // On frontend, ensure at least one localised version exists in localised base table (404 otherwise)
        if (FluentState::singleton()->getIsFrontend()) {
            $wheres = [];
            $joinLocale = $locale;
            while ($joinLocale) {
                $joinAlias = $this->getLocalisedTable($this->owner->baseTable(), $joinLocale->Locale);
                $wheres[] = "\"{$joinAlias}\".\"ID\" IS NOT NULL";
                $joinLocale = $joinLocale->getParent();
            }
            $query->addWhereAny($wheres);
        }

        // Iterate through each select clause, replacing each with the translated version
        foreach ($query->getSelect() as $alias => $select) {
            // Skip fields without table context
            if (!preg_match('/^"(?<table>[\w\\\\]+)"\."(?<field>\w+)"$/i', $select, $matches)) {
                continue;
            }

            $table = $matches['table'];
            $field = $matches['field'];

            // If this table doesn't have translated fields then skip
            if (empty($tables[$table])) {
                continue;
            }

            // If this field shouldn't be translated, skip
            if (!in_array($field, $tables[$table])) {
                continue;
            }

            $expression = $this->localiseSelect($table, $field, $locale);
            $query->selectField($expression, $alias);
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
     * Get the localised table name with the localised suffix and optionally with a locale suffix for aliases
     *
     * @param string $tableName
     * @param string $locale
     * @return string
     */
    public function getLocalisedTable($tableName, $locale = '')
    {
        $localisedTable = $tableName . '_' . self::SUFFIX;
        if ($locale) {
            $localisedTable .= '_' . $locale;
        }
        return $localisedTable;
    }

    /**
     * Generates a select fragment based on a field with a fallback
     *
     * @param string $table
     * @param string $field
     * @param Locale $locale
     * @return string Select fragment
     */
    protected function localiseSelect($table, $field, Locale $locale)
    {
        $joinLocale = $locale;

        // Build case for each locale down the chain
        $query = "CASE\n";
        while ($joinLocale) {
            $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
            $query .= "\tWHEN \"{$joinAlias}\".\"ID\" IS NOT NULL THEN \"{$joinAlias}\".\"{$field}\"\n";
            $joinLocale = $joinLocale->getParent();
        }

        // Note: In CMS only we fall back to value in root table (in case not yet migrated)
        // On the frontend this row would have been filtered already (see augmentSQL logic)
        $sqlDefault = "\"{$table}\".\"{$field}\"";
        $this->owner->invokeWithExtensions('updateLocaliseSelectDefault', $sqlDefault, $table, $field, $locale);
        $query .= "\tELSE $sqlDefault END\n";

        // Fall back to null by default, but allow extensions to override this entire fragment
        // Note: Extensions are responsible for SQL escaping
        $this->owner->invokeWithExtensions('updateLocaliseSelect', $query, $table, $field, $locale);
        return $query;
    }
}
