<?php

namespace TractorCow\Fluent\Extension;

use LogicException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\Queries\SQLConditionGroup;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\HTML;
use TractorCow\Fluent\Extension\Traits\FluentObjectTrait;
use TractorCow\Fluent\Forms\CopyLocaleAction;
use TractorCow\Fluent\Forms\GroupActionMenu;
use TractorCow\Fluent\Model\Delete\UsesDeletePolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;
use TractorCow\Fluent\Service\CopyToLocaleService;
use TractorCow\Fluent\State\FluentState;

/**
 * Basic fluent extension
 *
 * When determining whether a field is localised, the following config options are checked in order:
 * - translate (uninherited, for each class in the chain)
 * - field_exclude
 * - field_include
 * - data_exclude
 * - data_include
 *
 * @template T of DataObject
 * @extends DataExtension<T&static>
 */
class FluentExtension extends DataExtension
{
    /**
     * Deletions are managed via DeletePolicy
     */
    use UsesDeletePolicy;
    use FluentObjectTrait;

    /**
     * The table suffix that will be applied to create localisation tables
     */
    const SUFFIX = 'Localised';

    /**
     * translate config key to disable localisations for this table
     */
    const TRANSLATE_NONE = 'none';

    /**
     * Content inheritance - content will be served from the following sources in this order:
     * current locale
     */
    const INHERITANCE_MODE_EXACT = 'exact';

    /**
     * Content inheritance - content will be served from the following sources in this order:
     * current locale, fallback locale
     */
    const INHERITANCE_MODE_FALLBACK = 'fallback';

    /**
     * Content inheritance - content will be served from the following sources in this order:
     * current locale, fallback locale, base record
     */
    const INHERITANCE_MODE_ANY = 'any';

    /**
     * DB fields to be used added in when creating a localised version of the owner's table
     *
     * @config
     * @var array
     */
    private static $db_for_localised_table = [
        'ID'       => 'PrimaryKey',
        'RecordID' => 'Int',
        'Locale'   => 'Varchar(10)',
    ];

    /**
     * Indexes to create on a localised version of the owner's table
     *
     * @config
     * @var array
     */
    private static $indexes_for_localised_table = [
        'Fluent_Record' => [
            'type'    => 'unique',
            'columns' => [
                'RecordID',
                'Locale',
            ],
        ],
    ];

    /**
     * List of fields to translate for this record
     *
     * Can be set to a list of fields, or a single string 'none' to disable all fields.
     * Not inherited, and must be set per class.
     *
     * If set takes priority over all white / black lists
     *
     * @var array|string
     */
    private static $translate = [];

    /**
     * Filter whitelist of fields to localise.
     * Note: Blacklist takes priority over whitelist.
     *
     * @config
     * @var array
     */
    private static $field_include = [];

    /**
     * Filter blacklist of fields to localise.
     * Note: Blacklist takes priority over whitelist.
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
     * Note: Blacklist takes priority over whitelist.
     *
     * @config
     * @var
     */
    private static $data_include = [
        'Text',
        'Varchar',
        'HTMLText',
        'HTMLVarchar',
        DBText::class,
        DBVarchar::class,
        DBHTMLText::class,
        DBHTMLVarchar::class,
    ];

    /**
     * Filter blacklist of field types to localise.
     * Note: Blacklist takes priority over whitelist.
     *
     * @config
     * @var array
     */
    private static $data_exclude = [];

    /**
     * Enable copy to locale action in the localisation manager
     *
     * @config
     * @var bool
     */
    private static $copy_to_locale_enabled = true;

    /**
     * Enable copy from locale action in the localisation manager
     *
     * @config
     * @var bool
     */
    private static $copy_from_locale_enabled = true;

    /**
     * Enable batch actions in the edit form
     *
     * @config
     * @var bool
     */
    private static $batch_actions_enabled = true;

    /**
     * Localised copy duplication config
     * example use: related object needs to be duplicated into the new locale
     * config syntax: <relation_name> => <relation_id>
     *
     * @var array
     */
    private static $localised_copy = [];

    /**
     * Enable localise actions (copy to draft, copy & publish and Localise actions)
     * these actions can be used to localise page content directly via main page actions
     *
     * @config
     * @var bool
     */
    private static $localise_actions_enabled = true;

    /**
     * Cache of localised fields for this model
     */
    protected $localisedFields = [];

    /**
     * Global state of localised copy feature
     *
     * @var bool
     */
    protected $localisedCopyActive = true;

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
        if ($filter === FluentExtension::TRANSLATE_NONE || empty($fields)) {
            return $this->localisedFields[$class] = [];
        }

        // filter out DB
        foreach ($fields as $field => $type) {
            if (!$this->isFieldLocalised($field, $type, $class)) {
                unset($fields[$field]);
            }
        }

        return $this->localisedFields[$class] = $fields;
    }

    /**
     * Check if a field is marked for localisation
     *
     * @param string $field Field name
     * @param string $type Field type
     * @param string $class Class this field is defined in
     * @return bool
     */
    protected function isFieldLocalised($field, $type, $class)
    {
        // Explicit per-table filter
        $filter = Config::inst()->get($class, 'translate', Config::UNINHERITED);
        if ($filter === FluentExtension::TRANSLATE_NONE) {
            return false;
        }
        if ($filter && is_array($filter)) {
            return in_array($field, $filter);
        }

        // Named blacklist
        $fieldsExclude = Config::inst()->get($class, 'field_exclude');
        if ($fieldsExclude && $this->anyMatch($field, $fieldsExclude)) {
            return false;
        }

        // Named whitelist
        $fieldsInclude = Config::inst()->get($class, 'field_include');
        if ($fieldsInclude && $this->anyMatch($field, $fieldsInclude)) {
            return true;
        }

        // Typed blacklist
        $dataExclude = Config::inst()->get($class, 'data_exclude');
        if ($dataExclude && $this->anyMatch($type, $dataExclude)) {
            return false;
        }

        // Typed whitelist
        $dataInclude = Config::inst()->get($class, 'data_include');
        if ($dataInclude && $this->anyMatch($type, $dataInclude)) {
            return true;
        }

        return false;
    }

    /**
     * Get all database tables in the class ancestry and their respective
     * translatable fields
     *
     * @return array
     */
    public function getLocalisedTables()
    {
        $includedTables = [];
        $baseClass = $this->owner->baseClass();
        $tableClasses = ClassInfo::ancestry($this->owner, true);
        foreach ($tableClasses as $class) {
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

        // Config check - subclasses should not have this extension applied
        if ($class !== $baseClass && !$this->validateChildConfig()) {
            return;
        }

        // Config check - Class with multiple extensions applied
        if (!$this->validateBaseConfig()) {
            return;
        }

        // Don't require table if no fields and not base class
        $localisedFields = $this->getLocalisedFields($class);
        $localisedTable = $this->getLocalisedTable($schema->tableName($class));
        if (empty($localisedFields) && $class !== $baseClass) {
            $this->augmentDatabaseDontRequire($localisedTable);
            return;
        }

        // Merge fields and indexes
        $fields = array_merge(
            $this->owner->config()->get('db_for_localised_table'),
            $localisedFields
        );
        $indexes = $this->owner->config()->get('indexes_for_localised_table');
        $this->augmentDatabaseRequireTable($localisedTable, $fields, $indexes);
    }


    /**
     * Ensure only one instance of this extension is applied to this class
     *
     * @return bool
     */
    protected function validateBaseConfig()
    {
        $fluents = 0;
        $extensions = $this->owner->get_extensions();
        foreach ($extensions as $extension) {
            if (is_a($extension, FluentExtension::class, true)) {
                $fluents++;
            }
        }
        if ($fluents > 1) {
            $name = get_class($this->owner);
            DB::alteration_message("Invalid config: {$name} has multiple FluentExtensions applied", 'error');
            return false;
        }
        return true;
    }

    /**
     * Non-base classes should never have fluent applied; Do this at the root only!
     *
     * @return bool
     */
    protected function validateChildConfig()
    {
        // Get uninherited extensions
        $extensions = Config::forClass($this->owner)
            ->get(
                'extensions',
                Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED
            ) ?: [];
        $extensions = array_filter(array_values($extensions));
        foreach ($extensions as $extension) {
            $extensionClass = Extension::get_classname_without_arguments($extension);
            if (is_a($extensionClass, FluentExtension::class, true)) {
                $name = get_class($this->owner);
                DB::alteration_message(
                    "Invalid config: {$name} has FluentExtension, but this should be applied only on the base class",
                    'error'
                );
                return false;
            }
        }
        return true;
    }

    protected function augmentDatabaseDontRequire($localisedTable)
    {
        DB::dont_require_table($localisedTable);
    }

    /**
     * Require the given localisation table
     *
     * @param string $localisedTable
     * @param array $fields
     * @param array $indexes
     */
    protected function augmentDatabaseRequireTable($localisedTable, $fields, $indexes)
    {
        DB::require_table($localisedTable, $fields, $indexes, false);
    }

    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        $locale = $this->getDataQueryLocale($dataQuery);
        if (!$locale) {
            return;
        }

        // Select locale as literal
        $query->selectField(Convert::raw2sql($locale->Locale, true), 'Locale');

        // Join all tables on the given locale code
        $tables = $this->getLocalisedTables();
        foreach ($tables as $table => $fields) {
            $localisedTable = $this->getLocalisedTable($table);
            // Join all items in ancestory
            foreach ($locale->getChain() as $joinLocale) {
                $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
                $query->addLeftJoin(
                    $localisedTable,
                    "\"{$table}\".\"ID\" = \"{$joinAlias}\".\"RecordID\" AND \"{$joinAlias}\".\"Locale\" = ?",
                    $joinAlias,
                    20,
                    [$joinLocale->Locale]
                );
            }
        }

        // Resolve content inheritance (this drives what content is shown)
        $inheritanceMode = $this->getInheritanceMode();
        if ($inheritanceMode === FluentExtension::INHERITANCE_MODE_EXACT) {
            $joinAlias = $this->getLocalisedTable($this->owner->baseTable(), $locale->Locale);
            $where = sprintf('"%s"."ID" IS NOT NULL', $joinAlias);
            $query->addWhereAny($where);
        } elseif ($inheritanceMode === FluentExtension::INHERITANCE_MODE_FALLBACK) {
            $conditions = [];

            foreach ($locale->getChain() as $joinLocale) {
                $joinAlias = $this->getLocalisedTable($this->owner->baseTable(), $joinLocale->Locale);
                $conditions[] = sprintf('"%s"."ID" IS NOT NULL', $joinAlias);
            }

            $query->addWhereAny($conditions);
        }

        // Add the "source locale", which the content exists in up the chain
        $sourceLocaleQuery = 'CASE ';
        foreach ($locale->getChain() as $joinLocale) {
            $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
            $localeSQL = Convert::raw2sql($joinLocale->Locale);
            $sourceLocaleQuery .= "\tWHEN \"{$joinAlias}\".\"ID\" IS NOT NULL THEN '{$localeSQL}' \n";
        }
        $sourceLocaleQuery .= 'ELSE NULL END';
        $query->selectField($sourceLocaleQuery, 'SourceLocale');

        // Iterate through each select clause, replacing each with the translated version
        foreach ($query->getSelect() as $alias => $select) {
            // Parse fragment for localised field and table
            list ($table, $field) = $this->detectLocalisedTableField($tables, $select);
            if ($table && $field) {
                $expression = $this->localiseSelect($table, $field, $locale);
                $query->selectField($expression, $alias);
            }
        }

        // Build all replacements for where / sort conditions
        $conditionSearch = [];
        $conditionReplace = [];
        foreach ($tables as $table => $fields) {
            foreach ($fields as $field) {
                $conditionSearch[] = "\"{$table}\".\"{$field}\"";
                $conditionReplace[] = $this->localiseCondition($table, $field, $locale);
            }
        }

        // Iterate through each order clause, replacing each with the translated version
        $order = $query->getOrderBy();
        foreach ($order as $column => $direction) {
            // Parse fragment for localised field and table
            list ($table, $field, $fqn) = $this->detectLocalisedTableField($tables, $column);
            if ($table && $field) {
                $localisedColumn = $column;
                // Fix non-fully-qualified name
                if (!$fqn) {
                    $localisedColumn = str_replace(
                        "\"{$field}\"",
                        "\"{$table}\".\"{$field}\"",
                        $localisedColumn
                    );
                }
                // Apply substitutions
                $localisedColumn = str_replace($conditionSearch, $conditionReplace, $localisedColumn);
                if ($column !== $localisedColumn) {
                    // Wrap sort in group to prevent dataquery messing it up
                    unset($order[$column]);
                    $order["({$localisedColumn})"] = $direction;
                } else {
                    unset($order[$column]);
                    $order[$column] = $direction;
                }
            } else {
                unset($order[$column]);
                $order[$column] = $direction;
            }
        }
        $query->setOrderBy($order);

        // Rewrite where conditions
        $where = $query->getWhere();
        foreach ($where as $index => $condition) {
            // Extract parameters from condition
            if ($condition instanceof SQLConditionGroup) {
                $parameters = array();
                $predicate = $condition->conditionSQL($parameters);
            } else {
                $parameters = array_values(reset($condition));
                $predicate = key($condition);
            }

            // Apply substitutions
            $localisedPredicate = str_replace($conditionSearch, $conditionReplace, $predicate);
            
            if (empty($localisedPredicate)) {
                continue;
            }
            
            $where[$index] = [
                $localisedPredicate => $parameters
            ];
        }
        $query->setWhere($where);
    }

    /**
     * Force all changes, since we may need to cross-publish unchanged records between locales. Without this,
     * loading a page in a different locale and pressing "save" won't actually make the record available in
     * this locale.
     */
    public function onBeforeWrite(): void
    {
        $owner = $this->owner;
        $currentLocale = FluentState::singleton()->getLocale();

        if (!$currentLocale) {
            return;
        }

        $this->makeLocalisedCopy();

        // If the record is not versioned, force change
        if (!$owner->hasExtension(FluentVersionedExtension::class)) {
            $owner->forceChange();
            return;
        }

        // Force a change if the record doesn't already exist in the current locale
        if (!$owner->existsInLocale($currentLocale)) {
            $owner->forceChange();
        }
    }

    /**
     * @throws ValidationException
     */
    public function onAfterWrite(): void
    {
        $this->handleClassChanged();
    }

    /**
     * If an object is changed to another class, we should trigger localised copy
     *
     * @throws ValidationException
     */
    protected function handleClassChanged(): void
    {
        $owner = $this->owner;
        $stage = Versioned::get_stage() ?: Versioned::DRAFT;
        $currentLocale = FluentState::singleton()->getLocale();

        if (!$this->localisedCopyActive) {
            return;
        }

        if (!$currentLocale) {
            return;
        }

        if (!$owner->isChanged('ClassName')) {
            // ClassName did not change so we can bail out
            return;
        }

        if (!$owner->isInDB() || !$owner->existsInLocale()) {
            // This is just a sanity check
            return;
        }

        if ($stage !== Versioned::DRAFT) {
            // Only draft stage is relevant for the duplication
            return;
        }

        // Get list of all localised instances of this model and duplicate relations if needed (if current one has it)
        foreach ($this->owner->Locales() as $recordLocale) {
            // Skip locales this record isn't localised in
            if (!$recordLocale->IsDraft()) {
                continue;
            }

            if ($recordLocale->getLocale() === $currentLocale) {
                // We only need to handle other locale instances, current locale doesn't need updates
                continue;
            }

            // Get version of this parent record in other locale
            $localisedRecord = $recordLocale->getRecord();
            if (!$localisedRecord) {
                // This is just a sanity check
                continue;
            }

            $relations = (array)$owner->config()->get('localised_copy');
            $hasRecordsToWrite = false;
            foreach ($relations as $relation) {
                // Get original localised object to copy
                $original = $owner->{$relation}();
                if (!$original instanceof DataObject || !$original->exists()) {
                    // Nothing to duplicate
                    continue;
                }

                /** @var DataObject $localisedRelation */
                $localisedRelation = $localisedRecord->{$relation}();

                if ($localisedRelation instanceof DataObject
                    && $localisedRelation->exists()
                    && ((int)$original->ID !== (int)$localisedRelation->ID
                        || get_class($original) !== get_class($localisedRelation))
                ) {
                    // Relation is already available on the localised record, let's keep it
                    continue;
                }

                $duplicate = $original->duplicate();
                // Attach the duplicated relation to localised record
                $localisedRecord->setComponent($relation, $duplicate);
                $hasRecordsToWrite = true;
            }

            // Update localised record if any localised copies took place
            if ($hasRecordsToWrite) {
                FluentState::singleton()->withState(
                    static function (FluentState $state) use ($recordLocale, $localisedRecord): void {
                        $state->setLocale($recordLocale->getLocale());
                        $localisedRecord->write();
                    }
                );
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws LogicException if the manipulation table's ID is missing
     */
    public function augmentWrite(&$manipulation)
    {
        $locale = Locale::getCurrentLocale();
        if (!$locale) {
            return;
        }

        // Get all tables to translate fields for, and their respective field names
        $includedTables = $this->getLocalisedTables();
        foreach ($includedTables as $table => $localisedFields) {
            $localeTable = $this->getLocalisedTable($table);
            $this->localiseManipulationTable(
                $manipulation,
                $table,
                $localeTable,
                $localisedFields,
                $locale
            );
        }
    }

    /**
     * Localise a database manipluation from one table to another
     *
     * @param array $manipulation
     * @param string $table Table in manipulation to copy from
     * @param string $localeTable Table to copy manipulation to
     * @param array $localisedFields List of fields to filter write to
     * @param Locale $locale
     */
    protected function localiseManipulationTable(&$manipulation, $table, $localeTable, $localisedFields, Locale $locale)
    {
        // Skip if manipulation table, or fields, are empty
        if (empty($manipulation[$table]['fields'])) {
            return;
        }

        // Get ID field
        $updates = $manipulation[$table];
        $id = $this->getManipulationRecordID($updates);
        if (!$id) {
            throw new LogicException("Missing record ID for table manipulation {$table}");
        }

        // Copy entire manipulation to the localised table
        $localisedUpdate = $updates;

        // Filter fields by localised fields
        $localisedUpdate['fields'] = array_intersect_key(
            $updates['fields'],
            array_combine($localisedFields, $localisedFields)
        );
        unset($localisedUpdate['fields']['id']);

        // Note: Even if no localised fields are modified, update base row anyway
        // to ensure correct localisation state can be determined

        // Populate Locale / RecordID fields
        $localisedUpdate['fields']['RecordID'] = $id;
        $localisedUpdate['fields']['Locale'] = $locale->getLocale();

        // Convert ID filter to RecordID / Locale
        unset($localisedUpdate['id']);
        $localisedUpdate['where'] = [
            "\"{$localeTable}\".\"RecordID\"" => $id,
            "\"{$localeTable}\".\"Locale\""   => $locale->getLocale(),
        ];

        // Save back modifications to the manipulation
        $manipulation[$localeTable] = $localisedUpdate;
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
        $localisedTable = $tableName . '_' . FluentExtension::SUFFIX;
        if ($locale) {
            $localisedTable .= '_' . $locale;
        }
        return $localisedTable;
    }

    /**
     * Public accessor for getDeleteTableTarget
     *
     * @param string $tableName
     * @param string $locale
     * @return string
     */
    public function deleteTableTarget($tableName, $locale = '')
    {
        return $this->getDeleteTableTarget($tableName, $locale);
    }

    /**
     * Get real table name for deleting records (Note: Must have all table replacements applied)
     *
     * @param string $tableName
     * @param string $locale If passed, this is the locale we wish to delete in. If empty this is the root table
     * @return string
     */
    protected function getDeleteTableTarget($tableName, $locale = '')
    {
        if (!$locale) {
            return $tableName;
        }
        // Note: For any locale, just return the real table without the alias
        return $this->getLocalisedTable($tableName);
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
        // Build case for each locale down the chain
        $query = "CASE\n";
        foreach ($locale->getChain() as $joinLocale) {
            $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
            $query .= "\tWHEN \"{$joinAlias}\".\"ID\" IS NOT NULL THEN \"{$joinAlias}\".\"{$field}\"\n";
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

    /**
     * Generate a where fragment based on a field with a fallback.
     * This will be used as a search replacement in all where conditions matching the "Table"."Field" name.
     * Note that unlike localiseSelect, this uses a simple COLASECLE() for performance and to reduce
     * overall query size.
     *
     * @param string $table
     * @param string $field
     * @param Locale $locale
     * @return string Localised where replacement
     */
    protected function localiseCondition($table, $field, Locale $locale)
    {
        // Build all items in chain
        $query = "COALESCE(";
        foreach ($locale->getChain() as $joinLocale) {
            $joinAlias = $this->getLocalisedTable($table, $joinLocale->Locale);
            $query .= "\"{$joinAlias}\".\"{$field}\", ";
        }

        // Use root table as default
        $sqlDefault = "\"{$table}\".\"{$field}\"";
        $this->owner->invokeWithExtensions('updatelocaliseConditionDefault', $sqlDefault, $table, $field, $locale);
        $query .= "$sqlDefault)";

        // Fall back to null by default, but allow extensions to override this entire fragment
        // Note: Extensions are responsible for SQL escaping
        $this->owner->invokeWithExtensions('updatelocaliseCondition', $query, $table, $field, $locale);
        return $query;
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
        $localeCode = $dataQuery->getQueryParam('Fluent.Locale') ?: FluentState::singleton()->getLocale();
        if ($localeCode) {
            return Locale::getByLocale($localeCode);
        }
        return null;
    }

    /**
     * Add / refresh fluent badges to all localised fields.
     * Note: Idempotent and safe to call multiple times
     *
     * @param FieldList $fields
     */
    public function updateFluentLocalisedFields(FieldList $fields)
    {
        // get all fields to translate and remove
        $translated = $this->getLocalisedTables();
        foreach ($translated as $table => $translatedFields) {
            foreach ($translatedFields as $translatedField) {
                // Find field matching this translated field
                // If the translated field has an ID suffix also check for the non-suffixed version
                // E.g. UploadField()
                $field = $fields->dataFieldByName($translatedField);
                if (!$field && preg_match('/^(?<field>\w+)ID$/', $translatedField, $matches)) {
                    $field = $fields->dataFieldByName($matches['field']);
                }
                if ($field) {
                    $this->updateFluentCMSField($field);
                }
            }
        }
    }

    /**
     * Get locale this record was originally queried from, or belongs to
     *
     * @return Locale|null
     */
    protected function getRecordLocale()
    {
        $localeCode = $this->owner->getSourceQueryParam('Fluent.Locale');
        if ($localeCode) {
            $locale = Locale::getByLocale($localeCode);
            if ($locale) {
                return $locale;
            }
        }
        return Locale::getCurrentLocale();
    }


    /**
     * Returns the source locale that will display the content for this record
     *
     * @return Locale|null
     */
    public function getSourceLocale()
    {
        $sourceLocale = $this->owner->getField('SourceLocale');
        if ($sourceLocale) {
            return Locale::getByLocale($sourceLocale);
        }
        return Locale::getDefault();
    }

    /**
     * Extract the RecordID value for the given write
     *
     * @param array $updates Updates for the current table
     * @return null|int Record ID, or null if not found
     */
    protected function getManipulationRecordID($updates)
    {
        if (isset($updates['id'])) {
            return $updates['id'];
        }
        if (isset($updates['fields']['ID'])) {
            return $updates['fields']['ID'];
        }
        if (isset($updates['fields']['RecordID'])) {
            return $updates['fields']['RecordID'];
        }
        return null;
    }

    /**
     * Templatable list of all locale information for this record
     *
     * @return ArrayList<RecordLocale>
     */
    public function Locales()
    {
        $data = [];
        foreach (Locale::getCached() as $localeObj) {
            $data[] = $this->owner->LocaleInformation($localeObj->getLocale());
        }
        return ArrayList::create($data);
    }

    /**
     * Retrieves information about this object in the specified locale
     *
     * @param string $locale The locale (code) information to request, or null to use the default locale
     * @return RecordLocale
     */
    public function LocaleInformation($locale = null)
    {
        // Check locale and get object
        if ($locale) {
            $localeObj = Locale::getByLocale($locale);
        } else {
            $localeObj = Locale::getDefault();
        }

        return RecordLocale::create($this->owner, $localeObj);
    }

    /**
     * Get list of locales where record is localised in draft mode
     *
     * @return array
     */
    public function getLocaleInstances(): array
    {
        $locales = [];
        foreach ($this->owner->Locales() as $info) {
            if ($info->IsDraft()) {
                $locales[] = $info->getLocaleObject();
            }
        }
        return $locales;
    }

    /**
     * Determine the baseurl within a specified $locale.
     *
     * @param string $locale Locale
     * @return string
     */
    public function BaseURLForLocale($locale)
    {
        $localeObject = Locale::getByLocale($locale);
        if (!$localeObject) {
            return null;
        }
        return $localeObject->getBaseURL();
    }

    /**
     * Ensure has_one cache is segmented by locale
     *
     * @return string
     */
    public function cacheKeyComponent()
    {
        return 'fluentlocale-' . FluentState::singleton()->getLocale();
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        // Add all fluent tags for localised fields
        $this->updateFluentLocalisedFields($fields);

        // Update all core fluent fields
        $this->updateFluentCMSFields($fields);
    }

    /**
     * Add fluent tooltip to given field.
     * You can use this to add fluent tag to custom fields.
     *
     * @param FormField $field
     */
    public function updateFluentCMSField(FormField $field)
    {
        if ($field->hasClass('fluent__localised-field')) {
            return;
        }

        $tooltip = _t(__CLASS__ . ".FLUENT_ICON_TOOLTIP", 'Translatable field');
        $title = $field->Title();
        $titleXML = $title instanceof DBField ? $title->forTemplate() : Convert::raw2xml($title);
        $tooltip = DBField::create_field(
            'HTMLFragment',
            HTML::createTag('span', ['class' => 'font-icon-translatable', 'title' => $tooltip])
            . $titleXML
        );

        $field->addExtraClass('fluent__localised-field');
        $field->setTitle($tooltip);
    }

    /**
     * Require that this record is saved in the given locale for it to be visible
     *
     * @return string
     */
    protected function getInheritanceMode(): string
    {
        $config = $this->owner->config();
        $inheritanceMode = FluentState::singleton()->getIsFrontend()
            ? $config->get('frontend_publish_required')
            : $config->get('cms_localisation_required');

        // Detect legacy type
        if (is_bool($inheritanceMode)) {
            $inheritanceMode = $inheritanceMode
                ? FluentExtension::INHERITANCE_MODE_EXACT
                : FluentExtension::INHERITANCE_MODE_ANY;
        }

        if (!in_array($inheritanceMode, [
            FluentExtension::INHERITANCE_MODE_EXACT,
            FluentExtension::INHERITANCE_MODE_FALLBACK,
            FluentExtension::INHERITANCE_MODE_ANY,
        ])) {
            // Default mode
            $inheritanceMode = FluentExtension::INHERITANCE_MODE_ANY;
        }

        return $inheritanceMode;
    }

    /**
     * Detect a localised field within a SQL fragment.
     * Works with either select / sort fragments
     *
     * If successful, return an array [ thetable, thefield, fqn ]
     * Otherwise [ null, null ]
     *
     * @param array $tables Map of known table and nested fields to search
     * @param string $sql The SQL string to inspect
     * @return array Three item array with table and field and a flag for whether the fragment is fully quolified
     */
    protected function detectLocalisedTableField($tables, $sql)
    {
        // Check explicit "table"."field" within the fragment
        if (preg_match('/"(?<table>[\w\\\\]+)"\."(?<field>\w+)"/i', $sql, $matches)) {
            $table = $matches['table'];
            $field = $matches['field'];

            // Ensure both table and this field are valid
            if (empty($tables[$table]) || !in_array($field, $tables[$table])) {
                return [null, null, false];
            }
            return [$table, $field, true];
        }

        // Check sole "field" without table specifier ("name" without leading or trailing '.')
        if (preg_match('/(?<![.])"(?<field>\w+)"(?![.])/i', $sql, $matches)) {
            $field = $matches['field'];

            // Check if this field is in any of the tables, and just pick any that match
            foreach ($tables as $table => $fields) {
                if (in_array($field, $fields)) {
                    return [$table, $field, false];
                }
            }
        }

        return [null, null, false];
    }

    /**
     * Returns the selected language
     *
     * @return RecordLocale
     */
    public function getSelectedLanguage()
    {
        return $this->LocaleInformation(FluentState::singleton()->getLocale());
    }

    /**
     * Check if this record exists (in either state) in this locale
     *
     * @param string $locale
     * @return bool
     */
    public function existsInLocale($locale = null)
    {
        if (!$this->owner->ID) {
            return false;
        }

        // Check locale exists
        $locale = $locale ?: FluentState::singleton()->getLocale();
        if (!$locale) {
            return false;
        }

        // Get table, check if record is saved in the given locale
        $baseTable = $this->owner->baseTable();
        $table = $this->getLocalisedTable($baseTable);
        return $this->findRecordInLocale($locale, $table, $this->owner->ID);
    }

    /**
     * Checks whether the given record ID exists in the given locale, in the given table. Skips using the ORM because
     * we don't need it for this call.
     *
     * @param string $locale
     * @param string $table
     * @param int $id
     * @return bool
     */
    protected function findRecordInLocale($locale, $table, $id)
    {
        $query = SQLSelect::create('"ID"');
        $query->addFrom('"' . $table . '"');
        $query->addWhere([
            '"RecordID"' => $id,
            '"Locale"'   => $locale,
        ]);

        return $query->firstRow()->execute()->value() !== null;
    }

    /**
     * @param $summaryColumns
     * @see FluentObjectTrait::updateFluentCMSFields()
     */
    public function updateLocalisationTabColumns(&$summaryColumns)
    {
        $summaryColumns['Status'] = [
            'title'    => 'Status',
            'callback' => function (Locale $object) {
                if (!$object->RecordLocale()) {
                    return '';
                }

                if ($object->RecordLocale()->IsDraft()) {
                    return _t(FluentExtension::class . '.LOCALISED', 'Localised');
                }

                return _t(FluentExtension::class . '.NOTLOCALISED', 'Not localised');
            }
        ];

        $summaryColumns['Source'] = [
            'title'    => 'Source',
            'callback' => function (Locale $object) {
                if (!$object->RecordLocale()) {
                    return '';
                }

                $sourceLocale = $object->RecordLocale()->getSourceLocale();

                if ($sourceLocale) {
                    return $sourceLocale->getLongTitle();
                }

                return _t(FluentExtension::class . '.NOSOURCE', 'No source');
            }
        ];
    }

    /**
     * Add copy actions to each locale
     * Note that permissions for these actions are resolved within the GridField components themselves
     *
     * @param GridFieldConfig $config
     */
    public function updateLocalisationTabConfig(GridFieldConfig $config)
    {
        // Add locale copy actions
        $config->addComponents([
            new GroupActionMenu(
                CopyLocaleAction::COPY_FROM,
                _t(__CLASS__ . '.COPY_FROM', 'Copy to {locale} from:')
            ),
            new GroupActionMenu(
                CopyLocaleAction::COPY_TO,
                _t(__CLASS__ . '.COPY_TO', 'Copy from {locale} to:')
            ),
            // Force other items into a separate group :)
            new GroupActionMenu(GridField_ActionMenuItem::DEFAULT_GROUP)
        ]);

        $copyToLocaleEnabled = $this->owner->config()->get('copy_to_locale_enabled');
        $copyFromLocaleEnabled = $this->owner->config()->get('copy_from_locale_enabled');

        // Add each copy from / to
        foreach (Locale::getCached() as $locale) {
            if ($copyToLocaleEnabled) {
                $config->addComponents([
                    CopyLocaleAction::create($locale->Locale, true),
                ]);
            }

            if ($copyFromLocaleEnabled) {
                $config->addComponents([
                    CopyLocaleAction::create($locale->Locale, false),
                ]);
            }
        }
    }

    public function getLocalisedCopyActive(): bool
    {
        return $this->localisedCopyActive;
    }

    public function setLocalisedCopyActive(bool $active): DataObject
    {
        $this->localisedCopyActive = $active;

        return $this->owner;
    }

    /**
     * Localised copy global state manipulation
     * useful for disabling localised copy feature in parts of the code
     *
     * @param callable $callback
     * @return mixed
     */
    public function withLocalisedCopyState(callable $callback)
    {
        $active = $this->localisedCopyActive;

        try {
            return $callback();
        } finally {
            $this->localisedCopyActive = $active;
        }
    }

    /**
     * Copy data object content from current locale to the target locale
     *
     * @param string $toLocale
     * @throws ValidationException
     */
    public function copyToLocale(string $toLocale): void
    {
        $owner = $this->owner;
        $fromLocale = FluentState::singleton()->getLocale();
        CopyToLocaleService::singleton()->copyToLocale($owner->ClassName, $owner->ID, $fromLocale, $toLocale);
    }

    /**
     * Duplicate related objects based on configuration
     * Provides an extension hook for custom duplication
     */
    protected function makeLocalisedCopy(): void
    {
        if (!$this->localisedCopyNeeded()) {
            return;
        }

        $owner = $this->owner;
        $relations = (array)$owner->config()->get('localised_copy');

        $owner->invokeWithExtensions('onBeforeLocalisedCopy');

        foreach ($relations as $relation) {
            $original = $owner->{$relation}();

            if (!$original instanceof DataObject) {
                continue;
            }

            if (!$original->exists()) {
                continue;
            }

            $duplicate = $original->duplicate();

            $owner->invokeWithExtensions('onBeforeLocalisedCopyRelation', $relation, $original, $duplicate);
            $owner->setComponent($relation, $duplicate);
            $owner->invokeWithExtensions('onAfterLocalisedCopyRelation', $relation, $original, $duplicate);
        }

        $owner->invokeWithExtensions('onAfterLocalisedCopy');
    }

    /**
     * Determine if localised copy is needed
     *
     * @return bool
     */
    protected function localisedCopyNeeded(): bool
    {
        if (!$this->localisedCopyActive) {
            return false;
        }

        $owner = $this->owner;
        $stage = Versioned::get_stage() ?: Versioned::DRAFT;

        if ($stage !== Versioned::DRAFT) {
            // only draft stage is relevant for the duplication
            return false;
        }

        if ($owner->isInDB() && !$owner->existsInLocale()) {
            // object has a base record and doesn't have a localised record and we are localising it
            return true;
        }

        $currentLocale = FluentState::singleton()->getLocale();
        $sourceLocale = $this->getRecordLocale();

        if (!$currentLocale || !$sourceLocale) {
            return false;
        }

        if ($owner->existsInLocale() && $currentLocale !== $sourceLocale->Locale) {
            // object has a localised record and the content is being overridden
            // from another locale (via copy to/from)
            // note that we can't rely on isChanged() because writeToStage() calls forceChange()
            // which would make this condition true every time
            return true;
        }

        // all other cases should not duplicate (normal edits)
        return false;
    }
}
