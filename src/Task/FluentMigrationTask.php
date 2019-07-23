<?php


namespace TractorCow\Fluent\Task;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

/**
 * Class FluentMigrationTask.
 *
 * Migrate content from Fluent 3.x format to 4.x
 *
 * THIS IS A WORK IN PROGRESS. USE AT YOUR OWN RISK.
 *
 * Assumptions:
 * - we still have the locales defined in Fluent.locales yml config, but we also have the locales defined in the db
 * - the default locale is defined in Fluent.locales yml config and matches that in the DB
 *
 * TODO:
 * - parameter for dry-run
 */
class FluentMigrationTask extends BuildTask
{

    protected $title = "Convert Fluent/SS3 > Fluent SS4 Task";

    protected $description = "Migrates site DB from SS3 Fluent DB format to SS4 Fluent.";

    private static $segment = 'FluentMigrationTask';


    /**
     * Parameters in order:
     *    - Localised table name with suffix
     *    - Version field selector (if 'Versions' table)
     *    - Field for record ID in source table (normally ID but RecordID in 'Versions' table)
     *    - Locale string
     *    - List of fields to select
     *    - Source table name
     *    - List of fields to update
     */
    const QUERY_TEMPLATE = "
        INSERT INTO `%s`
        SELECT
            %s
            NULL AS `ID`,
            `%s` AS `RecordID`,
            '%s' AS `Locale`,
            %s
        FROM `%s`
        %s
    ON DUPLICATE KEY UPDATE
        %s
    ;
    ";
    /**
     * List of suffixes to be applied to the base table names
     */
    const SUFFIXES = [
        'versioned' => [
            '',
            '_Live',
            '_Versions',
        ],
        'unversioned' => [
            ''
        ]

    ];
    protected $write = false;
    protected $migrate_sublcasses_of = DataObject::class;

    /**
     * @param $request
     */
    public function run($request)
    {
        $this->write = ($request->getVar('write') === 'true');

        $queries = $this->buildQueries();
        $this->runQueries($queries);
    }

    /**
     * @param $tableFields
     * @return array
     * @throws \Exception
     */
    protected function buildQueries()
    {
        $queries = [];
        foreach ($this->getLocales() as $locale) {
            $queries[$locale] = $this->buildQueriesForLocale($locale);
        }
        return $queries;
    }

    /**
     * We assume that the old config is still available to get the configured locales
     *
     * @return array
     * @throws \Exception
     */
    public function getLocales()
    {
        $locales = Config::inst()->get('Fluent', 'locales');

        if (empty($locales)) {
            throw new \Exception('Fluent.locales is required');
        }

        return $locales;
    }

    /**
     * We assume that the old config is still available to get the default locale
     *
     * @return array
     * @throws \Exception
     */
    public function getDefaultLocale()
    {
        $defaultLocale = Config::inst()->get('Fluent', 'default_locale');

        if (empty($defaultLocale)) {
            throw new \Exception('Fluent.default_locale is required');
        }

        return $defaultLocale;
    }

    /**
     * @param $tableFields
     * @param $locale
     * @return array
     */
    protected function buildQueriesForLocale($locale)
    {
        $localeQueries = [];
        $classes = $this->getFluentClasses();
        foreach ($classes as $class) {
            $currentInstance = singleton($class);
            $tableFields = $currentInstance->getLocalisedTables();

            $suffixes = $currentInstance->hasExtension(FluentVersionedExtension::class)
                ? self::SUFFIXES['versioned']
                : self::SUFFIXES['unversioned'];

            foreach ($tableFields as $table => $fields) {
                if (count($fields) > 0) {
                    foreach ($suffixes as $suffix) {
                        $localisedTable = "{$table}_Localised{$suffix}";
                        $sourceTable = "{$table}{$suffix}";
                        $selectFields = $this->buildSelectFieldList($sourceTable, $fields, $locale);
                        $updateFields = $this->buildUpdateFieldList($sourceTable, $fields, $locale);
                        $whereClause = ($locale == $this->getDefaultLocale())
                            ? ''
                            : $this->buildWhere($fields, $locale);
                        if ($suffix === '_Versions') {
                            $versionSelector = 'Version,';
                            $idField = 'RecordID';
                        } else {
                            $versionSelector = '';
                            $idField = 'ID';
                        }
                        $localeQueries[$localisedTable] = sprintf(
                            self::QUERY_TEMPLATE,
                            $localisedTable,
                            $versionSelector,
                            $idField,
                            $locale,
                            $selectFields,
                            $sourceTable,
                            $whereClause,
                            $updateFields
                        );
                    }
                }
            }
        }

        return $localeQueries;
    }

    /**
     * Get all sub-classes of DataObject that have FluentExtension applied
     *
     * @return array    Class names
     */
    protected function getFluentClasses()
    {
        $dataObjects = ClassInfo::subclassesFor($this->migrate_sublcasses_of);
        return array_filter(
            array_values($dataObjects),
            function ($className) {
                return call_user_func([$className, 'has_extension'], FluentExtension::class);
            }
        );
    }

    /**
     * @param $fields
     * @param $locale
     * @return string
     */
    protected function buildSelectFieldList($sourceTable, $fields, $locale)
    {
        return implode(
            ', ',
            array_map(
                function ($field) use ($locale, $sourceTable) {
                    return sprintf('COALESCE(`%s_%s`, `%s`.`%s`) AS `%s`', $field, $locale, $sourceTable, $field, $field);
                },
                $fields
            )
        );
    }

    /**
     * @param $fields
     * @param $locale
     * @return string
     */
    protected function buildUpdateFieldList($sourceTable, $fields, $locale)
    {
        return implode(
            ', ',
            array_map(
                function ($field) use ($locale, $sourceTable) {
                    return sprintf('`%s` = COALESCE(`%s_%s`, `%s`.`%s`)', $field, $field, $locale, $sourceTable, $field);
                },
                $fields
            )
        );
    }

    /**
     * Build a where clause to ensure we only get locales that have translations
     * for one or more fields
     *
     * @param string $sourceTable
     * @param array $fields
     * @param string $locale
     * @return string
     */
    protected function buildWhere($fields, $locale)
    {
        $whereFields = implode(
            ' OR ',
            array_map(
                function ($field) use ($locale) {
                    return sprintf('`%s_%s` IS NOT NULL', $field, $locale);
                },
                $fields
            )
        );
        return "WHERE ({$whereFields})";
    }

    /**
     * @param $queries
     */
    protected function runQueries($queries)
    {
        foreach ($queries as $locale => $localeQueries) {
            echo "\nRunning queries for locale '{$locale}'\n\n";

            foreach ($localeQueries as $table => $query) {
                echo "Updating table '{$table}'\n";
                if ($this->write === true) {
                    try {
                        DB::query($query);
                    } catch (DatabaseException $e) {
                        echo $e->getMessage();
                    }
                } else {
                    echo $query;
                }
            }
        }
    }

    /**
     * Setter for testing... or if you want to migrate only a specific dataobject
     *
     * @param string $dataobject
     */
    public function setMigrateSubclassesOf($dataobject)
    {
        $this->migrate_sublcasses_of = $dataobject;
        return $this;
    }
}
