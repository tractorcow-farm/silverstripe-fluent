<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/**
 * Basic fluent extension
 */
class FluentExtension extends Extension
{
    const SUFFIX = '_Localised';

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
     * Helper function to check if the value given is present in any of the patterns.
     * This function is case sensitive by default.
     *
     * @param string $value A string value to check against, potentially with parameters (E.g. 'Varchar(1023)')
     * @param array $patterns A list of strings, some of which may be regular expressions
     * @return boolean True if this $value is present in any of the $patterns
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
        $schema = DataObject::getSchema();

        // Don't require table if no fields
        $localisedFields = $this->getLocalisedFields($class);
        $localisedTable = $schema->tableName($class) . self::SUFFIX;
        if (empty($localisedFields)) {
            DB::dont_require_table($localisedTable);
            return;
        }

        // Add extra fields
        $fields = array_merge(
            [
                'ID' => 'PrimaryKey',
                'Locale' => 'Varchar(10)'
            ],
            $localisedFields
        );
        $indexes = [
            'PK' => [
                'type' => 'unique',
                'columns' => [
                    'ID',
                    'Locale',
                ],
            ],
        ];
        DB::require_table($localisedTable, $fields, $indexes, false);
    }
}
