<?php

namespace TractorCow\Fluent\Extension;

use LogicException;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\Queries\SQLConditionGroup;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\View\ArrayData;
use TractorCow\Fluent\Model\Locale;
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
 * @property FluentSiteTreeExtension|DataObject $owner
 */
class FluentExtension extends DataExtension
{
    /**
     * The table suffix that will be applied to create localisation tables
     */
    const SUFFIX = 'Localised';

    /**
     * translate config key to disable localisations for this table
     */
    const TRANSLATE_NONE = 'none';

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
     * Cache of localised fields for this model
     */
    protected $localisedFields = [];

    /**
     * @var bool
     */
    private static $frontend_publish_required = true;

    /**
     * Controls whether the message indicating whether you are editing in the default locale is disabled
     *
     * @config
     * @var bool
     */
    private static $disable_current_locale_message = false;

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
        if ($filter === self::TRANSLATE_NONE || empty($fields)) {
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
        if ($filter === self::TRANSLATE_NONE) {
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
    protected function getLocalisedTables()
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
                    [ $joinLocale->Locale ]
                );
            }
        }

        // On frontend only show if published in this specific locale
        if ($this->owner->config()->get('frontend_publish_required') && FluentState::singleton()->getIsFrontend()) {
            $joinAlias = $this->getLocalisedTable($this->owner->baseTable(), $locale->Locale);
            $where = "\"{$joinAlias}\".\"ID\" IS NOT NULL";
            $query->addWhereAny($where);
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

        // Build all replacements for where conditions
        $conditionSearch = [];
        $conditionReplace = [];
        foreach ($tables as $table => $fields) {
            foreach ($fields as $field) {
                $conditionSearch[] = "\"{$table}\".\"{$field}\"";
                $conditionReplace[] = $this->localiseCondition($table, $field, $locale);
            }
        }

        // Rewrite where conditions
        $where = $query
            ->getWhere();
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

            $where[$index] = array(
                $localisedPredicate => $parameters
            );
        }
        $query->setWhere($where);
    }

    /**
     * Override delete behaviour
     *
     * @param array $queriedTables
     */
    public function updateDeleteTables(&$queriedTables)
    {
        // Ensure a locale exists
        $locale = Locale::getCurrentLocale();
        if (!$locale) {
            return;
        }

        // Fluent takes over deletion of objects
        $queriedTables = [];
        $localisedTables = $this->getLocalisedTables();
        $tableClasses = ClassInfo::ancestry($this->owner, true);
        foreach ($tableClasses as $class) {
            // Check main table name
            $table = DataObject::getSchema()->tableName($class);

            // Create root table delete
            $rootTable = $this->getDeleteTableTarget($table);
            $rootDelete = SQLDelete::create("\"{$rootTable}\"");

            // If table isn't localised, simple delete
            if (!isset($localisedTables[$table])) {
                $rootDelete->execute();
                continue;
            }

            // Remove _Localised record
            $localisedTable = $this->getDeleteTableTarget($table, $locale);
            $localisedDelete = SQLDelete::create(
                "\"{$localisedTable}\"",
                [
                    '"Locale"' => $locale->Locale,
                    '"RecordID"' => $this->owner->ID,
                ]
            );
            $localisedDelete->execute();

            // Remove orphaned ONLY base table (delete after deleting last localised row)
            // Note: No "Locale" filter as we are excluding any tables that have any localised records
            $rootDelete
                ->setDelete("\"{$rootTable}\"")
                ->addLeftJoin(
                    $localisedTable,
                    "\"{$rootTable}\".\"ID\" = \"{$localisedTable}\".\"RecordID\""
                )
                // Only when join matches no localisations is it safe to delete
                ->addWhere("\"{$localisedTable}\".\"ID\" IS NULL")
                ->execute();
        }
    }

    /**
     * Force all changes, since we may need to cross-publish unchanged records between locales
     */
    public function onBeforeWrite()
    {
        $this->owner->forceChange();
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

        // Skip if no fields are being saved after filtering
        if (empty($localisedUpdate['fields'])) {
            return;
        }

        // Populate Locale / RecordID fields
        $localisedUpdate['fields']['RecordID'] = $id;
        $localisedUpdate['fields']['Locale'] = $locale->getLocale();

        // Convert ID filter to RecordID / Locale
        unset($localisedUpdate['id']);
        $localisedUpdate['where'] = [
            "\"{$localeTable}\".\"RecordID\"" => $id,
            "\"{$localeTable}\".\"Locale\"" => $locale->getLocale(),
        ];

        // Save back modifications to the manipulation
        $manipulation[$localeTable] = $localisedUpdate;
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
     * Templatable list of all locales
     *
     * @return ArrayList
     */
    public function Locales()
    {
        $data = [];
        foreach (Locale::getCached() as $localeObj) {
            /** @var Locale $localeObj */
            $data[] = $this->owner->LocaleInformation($localeObj->getLocale());
        }
        return ArrayList::create($data);
    }
    /**
     * Retrieves information about this object in the specified locale
     *
     * @param string $locale The locale (code) information to request, or null to use the default locale
     * @return ArrayData Mapped list of locale properties
     */
    public function LocaleInformation($locale = null)
    {
        // Check locale and get object
        if ($locale) {
            $localeObj = Locale::getByLocale($locale);
        } else {
            $localeObj = Locale::getDefault();
        }
        $locale = $localeObj->getLocale();

        // Check linking mode
        $linkingMode = $this->getLinkingMode($locale);

        // Check link
        $link = $this->LocaleLink($locale);

        // Store basic locale information
        return ArrayData::create([
            'Locale' => $locale,
            'LocaleRFC1766' => i18n::convert_rfc1766($locale),
            'URLSegment' => $localeObj->getURLSegment(),
            'Title' => $localeObj->getTitle(),
            'LanguageNative' => $localeObj->getNativeName(),
            'Language' => i18n::getData()->langFromLocale($locale),
            'Link' => $link,
            'AbsoluteLink' => $link ? Director::absoluteURL($link) : null,
            'LinkingMode' => $linkingMode
        ]);
    }

    /**
     * Return the linking mode for the current locale and object
     *
     * @param string $locale
     * @return string
     */
    public function getLinkingMode($locale)
    {
        if ($this->owner->hasMethod('canViewInLocale') && !$this->owner->canViewInLocale($locale)) {
            return 'invalid';
        }

        if ($locale === FluentState::singleton()->getLocale()) {
            return 'current';
        }

        return 'link';
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
     * @param string $locale
     * @return string
     */
    public function LocaleLink($locale)
    {
        // Skip dataobjects that do not have the Link method
        if (!$this->owner->hasMethod('Link')) {
            return null;
        }

        // Return locale root url if unable to view this item in this locale
        $defaultLink = $this->owner->BaseURLForLocale($locale);
        if ($this->owner->hasMethod('canViewInLocale') && !$this->owner->canViewInLocale($locale)) {
            return $defaultLink;
        }

        return FluentState::singleton()->withState(function (FluentState $newState) use ($locale, $defaultLink) {
            $newState->setLocale($locale);
            // Non-db records fall back to internal behaviour
            if (!$this->owner->isInDB()) {
                return $this->owner->Link();
            }

            // Reload this record in the correct locale
            $record = DataObject::get($this->owner->ClassName)->byID($this->owner->ID);
            if ($record) {
                return $record->Link();
            } else {
                // may not be published in this locale
                return $defaultLink;
            }
        });
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
        // get all fields to translate and remove
        $translated = $this->getLocalisedTables();

        $currentLocale = Locale::getCurrentLocale();
        $locale = $currentLocale->Locale;
        $language = $currentLocale ? strtok($locale, '_') : '';

        foreach ($translated as $table => $translatedFields) {
            foreach ($translatedFields as $translatedField) {
                // Find field matching this translated field
                // If the translated field has an ID suffix also check for the non-suffixed version
                // E.g. UploadField()
                $field = $fields->dataFieldByName($translatedField);
                if (!$field && preg_match('/^(?<field>\w+)ID$/', $translatedField, $matches)) {
                    $field = $fields->dataFieldByName($matches['field']);
                }

                // Highlight any translatable field
                if ($field && !$field->hasClass('LocalisedField')) {
                    // Add a language indicator next to the fluent icon
                    $title = $field->Title();

                    $titleClasses = 'fluent-locale-label';
                    $modifiedTitle = 'Using default locale value';

                    $field->setTitle(
                        DBHTMLText::create()->setValue(
                            sprintf(
                                '<span class="%s" title="%s">%s</span>%s',
                                $titleClasses,
                                $modifiedTitle,
                                $language,
                                $title
                            )
                        )
                    );

                    $field->addExtraClass('LocalisedField');
                }
            }
        }

        $this->addLocaleIndicatorMessage($fields);
    }

    /**
     * Adds a UI message to indicate whether you're editing in the default locale or not
     *
     * @param  FieldList $fields
     * @return $this
     */
    protected function addLocaleIndicatorMessage(FieldList $fields)
    {
        if (Config::inst()->get(__CLASS__, 'disable_current_locale_message')) {
            return $this;
        }

        // If the field is already present, don't add it a second time
        if ($fields->fieldByName('CurrentLocaleMessage')) {
            return $this;
        }

        $localeNames     = Locale::getCached()->map('Locale', 'Title')->toArray();
        $defaultLocale   = Locale::getDefault();
        $isDefaultLocale = ($defaultLocale === Locale::getCurrentLocale());
        $defaultLocaleKey = $defaultLocale ? $defaultLocale->Locale : '';
        $currentLocale = Locale::getCurrentLocale();
        $currentLocaleKey = $currentLocale ? $currentLocale->Locale : '';
        $messageClass    = ($isDefaultLocale) ? 'alert-success' : 'alert-info';
        $message         = ($isDefaultLocale)
            ? _t(__CLASS__.'DefaultLocale', 'This is the default locale')
            : _t(
                __CLASS__.'DefaultLocaleIs',
                'The default locale is {locale}',
                ['locale' => $localeNames[$defaultLocaleKey]]
            );

        $fields->unshift(
            LiteralField::create(
                'CurrentLocaleMessage',
                sprintf(
                    '<p class="alert %s">'
                    . _t(
                        __CLASS__.'.EditingIn',
                        'Please note: You are editing in {locale}.',
                        ['locale' => $localeNames[$currentLocaleKey]]
                    )
                    . ' %s.</p>',
                    $messageClass,
                    $message
                )
            )
        );

        return $this;
    }
}
