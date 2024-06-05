<?php

namespace TractorCow\Fluent\Extension;

use InvalidArgumentException;
use LogicException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Resettable;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Forms\PublishAction;
use TractorCow\Fluent\Forms\UnpublishAction;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Extension for versioned localised objects
 *
 * Important: If adding this to a custom object, this extension must be added AFTER the versioned extension.
 * Use yaml `after` to enforce this
 *
 * @template T of DataObject&Versioned
 * @extends FluentExtension<T&static>
 */
class FluentVersionedExtension extends FluentExtension implements Resettable
{
    /**
     * Live table suffix
     */
    const SUFFIX_LIVE = '_Live';

    /**
     * Versions table suffix
     */
    const SUFFIX_VERSIONS = '_Versions';

    /**
     * Indicates that record is missing in this locale and the cache search for it is complete
     * so we can avoid multiple lookups of a missing record
     */
    const CACHE_COMPLETE = '_complete';

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
            'type'    => 'unique',
            'columns' => [
                'RecordID',
                'Locale',
                'Version',
            ],
        ],
        // Needed to speedup version table joins which are used in Version related operations
        // such as isPublishedInLocale
        'Fluent_Version' => [
            'type' => 'index',
            'columns' => [
                'RecordID',
                'Version',
            ],
        ],
    ];

    /**
     * Cache for versions related data lookups
     *
     * @var array
     */
    protected $versionsCache = [];

    /**
     * Array of objectIds keyed by table (ie. stage) and locale. This knows ALL object IDs that exist in the given table
     * and locale.
     *
     * This is different from the above cache which caches the result per object - each array (keyed by locale & table)
     * will have ALL object IDs for that locale & table.
     *
     * static::$idsInLocaleCache[ $locale ][ $table(.FluentVersionedExtension::SUFFIX_LIVE) ][ $objectId ] = $objectId
     *
     * @var int[][][]
     */
    protected static $idsInLocaleCache = [];

    /**
     * Used to enable or disable the prepopulation of the locale content cache
     * Defaults to true.
     *
     * @config
     * @var boolean
     */
    private static $prepopulate_localecontent_cache = true;

    public function augmentDatabase()
    {
        // Safety check: This extension is added AFTER versioned
        $seenVersioned = false;
        foreach ($this->owner->getExtensionInstances() as $extension) {
            // Must see versioned
            if ($extension instanceof Versioned) {
                $seenVersioned = true;
            } elseif ($extension instanceof FluentVersionedExtension) {
                if (!$seenVersioned) {
                    throw new LogicException(
                        "FluentVersionedExtension must be added AFTER Versioned extension. Check "
                        . get_class($this->owner)
                    );
                }
            }
        }

        parent::augmentDatabase();
    }

    protected function augmentDatabaseDontRequire($localisedTable)
    {
        DB::dont_require_table($localisedTable);
        DB::dont_require_table($localisedTable . FluentVersionedExtension::SUFFIX_LIVE);
        DB::dont_require_table($localisedTable . FluentVersionedExtension::SUFFIX_VERSIONS);
    }

    protected function augmentDatabaseRequireTable($localisedTable, $fields, $indexes)
    {
        DB::require_table($localisedTable, $fields, $indexes, false);

        // _Live record
        DB::require_table($localisedTable . FluentVersionedExtension::SUFFIX_LIVE, $fields, $indexes, false);

        // Merge fields and indexes with Fluent defaults
        $versionsFields = array_merge($this->defaultVersionsFields, $fields);
        $versionsIndexes = array_merge($indexes, $this->defaultVersionsIndexes);

        DB::require_table($localisedTable . FluentVersionedExtension::SUFFIX_VERSIONS, $versionsFields, $versionsIndexes, false);
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException if an invalid versioned mode is provided
     */
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
            case 'latest_version_single':
            case 'version':
                $this->rewriteVersionedTables($query, $tables, $locale);
                $this->localiseVersionsLookup($query, $locale);
                break;
            default:
                throw new InvalidArgumentException("Bad value for query parameter Versioned.mode: {$versionedMode}");
        }
    }

    /**
     * Rewrite all joined tables
     *
     * @param SQLSelect $query
     * @param array     $tables
     * @param Locale    $locale
     */
    protected function rewriteVersionedTables(SQLSelect $query, array $tables, Locale $locale)
    {
        foreach ($tables as $tableName => $fields) {
            // Rename to _Versions suffixed versions
            $localisedTable = $this->getLocalisedTable($tableName);
            $query->renameTable($localisedTable, $localisedTable . FluentVersionedExtension::SUFFIX_VERSIONS);

            // Add the chain of locale fallbacks
            $this->addLocaleFallbackChain($query, $tableName, $locale);
        }
    }

    /**
     * Update all joins to include Version as well as Locale / Record
     *
     * @param SQLSelect $query
     * @param string    $tableName
     * @param Locale    $locale
     */
    protected function addLocaleFallbackChain(SQLSelect $query, $tableName, Locale $locale)
    {
        $baseTable = $this->owner->baseTable();

        foreach ($locale->getChain() as $joinLocale) {
            $joinAlias = $this->getLocalisedTable($tableName, $joinLocale->Locale);
            $versionTable = $baseTable . FluentVersionedExtension::SUFFIX_VERSIONS;

            $query->setJoinFilter(
                $joinAlias,
                "\"{$versionTable}\".\"RecordID\" = \"{$joinAlias}\".\"RecordID\" "
                . "AND \"{$joinAlias}\".\"Locale\" = ? "
                . "AND \"{$joinAlias}\".\"Version\" = \"{$versionTable}\".\"Version\""
            );
        }
    }

    /**
     * Rename all localised tables to the "live" equivalent name (note: alias remains unchanged)
     *
     * @param SQLSelect $query
     * @param array     $tables
     */
    protected function renameLocalisedTables(SQLSelect $query, array $tables)
    {
        foreach ($tables as $table => $fields) {
            $localisedTable = $this->getLocalisedTable($table);
            $query->renameTable($localisedTable, $localisedTable . FluentVersionedExtension::SUFFIX_LIVE);
        }
    }

    /**
     * Narrow down the list of versions down to only those which are related to current locale
     *
     * @param SQLSelect $query
     * @param Locale $locale
     */
    protected function localiseVersionsLookup(SQLSelect $query, Locale $locale)
    {
        $class = $this->owner->ClassName;
        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $baseTable = $schema->tableName($baseClass);

        /** @var DataObject|FluentExtension $singleton */
        $singleton = DataObject::singleton($class);

        $localisedTable = $singleton->getLocalisedTable($baseTable);
        $localisedAlias = sprintf('%s_%s', $localisedTable, $locale->Locale);

        $query->addWhere([sprintf('"%s"."Locale" = ?', $localisedAlias) => $locale->Locale]);
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
        $locale = Locale::getCurrentLocale();
        if (!$locale) {
            return;
        }

        // Get all tables to translate fields for, and their respective field names
        $includedTables = $this->getLocalisedTables();
        foreach ($includedTables as $table => $localisedFields) {
            // Localise both _Versions and _Live writes
            foreach ([FluentVersionedExtension::SUFFIX_LIVE, FluentVersionedExtension::SUFFIX_VERSIONS] as $suffix) {
                $versionedTable = $table . $suffix;
                $localisedTable = $this->getLocalisedTable($table) . $suffix;

                // Add extra case for "Version" column when localising Versions
                $localisedVersionFields = $localisedFields;
                if ($suffix === FluentVersionedExtension::SUFFIX_VERSIONS) {
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
            $table .= FluentVersionedExtension::SUFFIX_LIVE;
        }
        return $table;
    }

    /**
     * Check if this record is saved in this locale
     *
     * @param string $locale
     * @return bool
     */
    public function isDraftedInLocale($locale = null)
    {
        return $this->isLocalisedInStage(Versioned::DRAFT, $locale);
    }

    /**
     * Check if this record is published in this locale
     *
     * @param string $locale
     * @return bool
     */
    public function isPublishedInLocale($locale = null)
    {
        return $this->isLocalisedInStage(Versioned::LIVE, $locale);
    }

    /**
     * Check if this record exists (in either state) in this locale
     *
     * @param string $locale
     * @return bool
     */
    public function existsInLocale($locale = null)
    {
        $stage = Versioned::get_stage() ?: Versioned::DRAFT;

        if ($stage === Versioned::DRAFT) {
            return $this->isDraftedInLocale($locale);
        }

        return $this->isPublishedInLocale($locale);
    }

    /**
     * Check if this record has modifications in this locale
     * Fluent friendly version of @see Versioned::stagesDiffer()
     *
     * @param string|null $locale
     * @return bool
     */
    public function stagesDifferInLocale($locale = null): bool
    {
        $record = $this->owner;
        $id = $record->ID ?: $record->OldID;
        $class = get_class($record);

        // Need to check if it's versioned
        if (!$record->hasExtension(Versioned::class)) {
            return false;
        }

        // Need to check that it has stages and is not new
        if (!$id || !$record->hasStages()) {
            return false;
        }

        if (!$record->isDraftedInLocale($locale)) {
            // Record is not localised so there is nothing to check
            // This is because Localised version records can not be inherited from other locales via the fallbacks
            return false;
        }

        if (!$record->isPublishedInLocale($locale)) {
            // Record is drafted but not published so we know the stages are different
            return true;
        }

        $locale = $locale ?: ($this->getRecordLocale() ? $this->getRecordLocale()->Locale : null);

        // Potentially no Locales have been created in the system yet.
        if (!$locale) {
            return false;
        }

        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $stageTable = $schema->tableName($baseClass);

        $versionSuffix = FluentVersionedExtension::SUFFIX_VERSIONS;
        $liveTable = $stageTable . $versionSuffix;
        $stagedTable = $record->getLocalisedTable($stageTable) . $versionSuffix;

        // notes:
        // VL - Versions localised table
        // V - Versions table
        $query = <<<SQL
SELECT "VL"."Version"
FROM "$stagedTable" as "VL"
INNER JOIN "$liveTable" as "V"
    ON "VL"."RecordID" = "V"."RecordID"
    AND "VL"."Version" = "V"."Version"
WHERE "VL"."RecordID" = ?
AND "VL"."Locale" = ?
AND "V"."WasPublished" = ?
ORDER BY "VL"."Version" DESC
LIMIT 1
SQL;

        $draftVersion = DB::prepared_query($query, [
            $id,
            $locale,
            0,
        ])->value();

        $liveVersion = DB::prepared_query($query, [
            $id,
            $locale,
            1,
        ])->value();

        // When a object is published a draft version is also written
        // The same is not true for drafts so we know a draft version that's
        // higher than the live version means we have a true stage differs
        return $draftVersion > $liveVersion;
    }

    /**
     * Check to see whether or not a record exists for a specific Locale in a specific stage.
     *
     * @param string $stage  Version stage
     * @param string $locale Locale to check. Defaults to current locale.
     * @return bool
     */
    protected function isLocalisedInStage($stage, $locale = null)
    {
        // Get locale
        if (!$locale) {
            $locale = FluentState::singleton()->getLocale();

            // Potentially no Locales have been created in the system yet.
            if (!$locale) {
                return false;
            }
        }

        // Get table
        $baseTable = $this->owner->baseTable();
        $table = $this->getLocalisedTable($baseTable);
        if ($stage === Versioned::LIVE) {
            $table .= FluentVersionedExtension::SUFFIX_LIVE;
        }

        // Check for a cached item in the full list of all objects. These are populated optimistically.
        if (isset(static::$idsInLocaleCache[$locale][$table][$this->owner->ID])) {
            return (bool)static::$idsInLocaleCache[$locale][$table][$this->owner->ID];
        }

        if (!empty(static::$idsInLocaleCache[$locale][$table][static::CACHE_COMPLETE])) {
            return false;
        }

        // Set cache and return
        return static::$idsInLocaleCache[$locale][$table][$this->owner->ID]
            = $this->findRecordInLocale($locale, $table, $this->owner->ID);
    }

    /**
     * Clear internal static property caches
     */
    public function flushCache()
    {
        static::reset();
    }

    public function flushVersionsCache(): void
    {
        $this->versionsCache = [];
    }

    public static function reset()
    {
        static::$idsInLocaleCache = [];

        $singleton = singleton(static::class);
        $singleton->flushVersionsCache();
    }

    /**
     * Hook into {@link Hierarchy::prepopulateTreeDataCache}.
     *
     * @param DataList|array $recordList The list of records to prepopulate caches for. Null for all records.
     * @param array          $options    A map of hints about what should be cached. "numChildrenMethod" and
     *                                   "childrenMethod" are allowed keys.
     */
    public function onPrepopulateTreeDataCache($recordList = null, array $options = [])
    {
        if (!Config::inst()->get(FluentVersionedExtension::class, 'prepopulate_localecontent_cache')) {
            return;
        }

        // Prepopulating for a specific list of records hasn't been implemented yet and will have to rely on the
        // fallback implementation of caching per record.
        if ($recordList) {
            return;
        }

        FluentVersionedExtension::prepoulateIdsInLocale(FluentState::singleton()->getLocale(), $this->owner->baseClass());
    }

    /**
     * Prepopulate the cache of IDs in a locale, to optimise batch calls to isLocalisedInStage.
     *
     * @param string $locale
     * @param string $dataObjectClass
     * @param bool   $populateLive
     * @param bool   $populateDraft
     */
    public static function prepoulateIdsInLocale($locale, $dataObjectClass, $populateLive = true, $populateDraft = true)
    {
        // Get the table for the given DataObject class
        /** @var DataObject|FluentExtension $dataObject */
        $dataObject = DataObject::singleton($dataObjectClass);
        $table = $dataObject->getLocalisedTable($dataObject->baseTable());

        // If we already have items then we've been here before...
        if (isset(FluentVersionedExtension::$idsInLocaleCache[$locale][$table])) {
            return;
        }

        $tables = [];
        if ($populateDraft) {
            $tables[] = $table;
        }
        if ($populateLive) {
            $tables[] = $table . FluentVersionedExtension::SUFFIX_LIVE;
        }

        // Populate both the draft and live stages
        foreach ($tables as $table) {
            $select = SQLSelect::create(
                ['"RecordID"'],
                '"' . $table . '"',
                ['"Locale"' => $locale]
            );
            $result = $select->execute();
            $ids = $result->column('RecordID');

            // We need to execute ourselves as the param is lost from the subSelect
            FluentVersionedExtension::$idsInLocaleCache[$locale][$table] = array_combine($ids, $ids);
            FluentVersionedExtension::$idsInLocaleCache[$locale][$table][static::CACHE_COMPLETE] = true;
        }
    }

    public function updateLocalisationTabColumns(&$summaryColumns)
    {
        $summaryColumns['Status'] = [
            'title' => 'Status',
            'callback' => function (Locale $object) {
                if (!$object->RecordLocale()) {
                    return '';
                }

                $recordLocale = $object->RecordLocale();

                if ($recordLocale->getStagesDiffer()) {
                    return _t(FluentVersionedExtension::class . '.MODIFIED', 'Modified');
                }

                if ($recordLocale->IsPublished(true)) {
                    return _t(FluentVersionedExtension::class . '.PUBLISHED', 'Published');
                }

                if ($recordLocale->IsDraft()) {
                    return _t(FluentVersionedExtension::class . '.DRAFT', 'Draft');
                }

                return _t(FluentVersionedExtension::class . '.NOTLOCALISED', 'Not localised');
            }
        ];

        $summaryColumns['Source'] = [
            'title' => 'Source',
            'callback' => function (Locale $object) {
                if (!$object->RecordLocale()) {
                    return '';
                }

                $sourceLocale = $object->RecordLocale()->getSourceLocale();

                if ($sourceLocale) {
                    return $sourceLocale->getLongTitle();
                }

                return _t(FluentVersionedExtension::class . '.NOSOURCE', 'No source');
            }
        ];

        $summaryColumns['Live'] = [
            'title' => 'Live',
            'callback' => function (Locale $object) {
                if (!$object || !$object->RecordLocale()) {
                    return '';
                }

                return $object->RecordLocale()->IsPublished()
                    ? _t(FluentVersionedExtension::class . '.LIVEYES', 'Yes')
                    : _t(FluentVersionedExtension::class . '.LIVENO', 'No');
            }
        ];
    }

    /**
     * Add versioning extensions for gridfield
     *
     * @param GridFieldConfig $config
     */
    public function updateLocalisationTabConfig(GridFieldConfig $config)
    {
        parent::updateLocalisationTabConfig($config);

        // Add actions for publishing / unpublishing in locale
        $config->addComponents([
            UnpublishAction::create(),
            PublishAction::create(),
        ]);
    }

    /**
     * Extension point in @see Versioned::stagesDiffer()
     *
     * @param bool $stagesDiffer
     */
    public function updateStagesDiffer(bool &$stagesDiffer): void
    {
        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $stagesDiffer = $this->owner->stagesDifferInLocale($locale);
    }

    /**
     * Localise archived state
     * Extension point in @see Versioned::isArchived()
     *
     * @param bool $isArchived
     */
    public function updateIsArchived(bool &$isArchived): void
    {
        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $archived = $this->owner->isArchivedInLocale($locale);

        if ($archived === null) {
            return;
        }

        $isArchived = $archived;
    }

    /**
     * @param string|null $locale
     * @return bool|null
     */
    public function isArchivedInLocale(?string $locale = null): ?bool
    {
        $locale = $locale ?: FluentState::singleton()->getLocale();

        if (!$locale) {
            return null;
        }

        $owner = $this->owner;
        $class = $owner->ClassName;
        $id = $owner->ID ?: $owner->OldID;

        if (!$id) {
            return false;
        }

        if ($owner->existsInLocale($locale)) {
            return false;
        }

        return $owner->hasArchiveInLocale($locale);
    }

    /**
     * Check if the record has previously existed in a locale
     *
     * @param string|null $locale
     * @return bool|null
     */
    public function hasArchiveInLocale(string $locale = null): ?bool
    {
        $locale = $locale ?: FluentState::singleton()->getLocale();

        if (!$locale) {
            return null;
        }

        $owner = $this->owner;
        $class = $owner->ClassName;
        $id = $owner->ID ?: $owner->OldID;

        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $baseTable = $schema->tableName($baseClass);

        $localisedTable = $owner->getLocalisedTable($baseTable);
        $localisedVersionTable = $localisedTable . static::SUFFIX_VERSIONS;

        $query = SQLSelect::create(
            'COUNT(*)',
            sprintf('"%s"', $localisedVersionTable),
            [
                '"Locale"' => $locale,
                '"RecordID"' => $id,
            ]
        );

        return $query->execute()->value();
    }

    /**
     * Localise max version lookup
     * Extension point in @see Versioned::prepareMaxVersionSubSelect()
     *
     * @param SQLSelect $subSelect
     * @param DataQuery $dataQuery
     * @param bool $shouldApplySubSelectAsCondition
     */
    public function augmentMaxVersionSubSelect(
        SQLSelect $subSelect,
        DataQuery $dataQuery,
        bool $shouldApplySubSelectAsCondition
    ): void {
        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $owner = $this->owner;
        $class = $owner->ClassName;

        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $baseTable = $schema->tableName($baseClass);

        $localisedTable = $owner->getLocalisedTable($baseTable);
        $localisedVersionTable = $localisedTable . static::SUFFIX_VERSIONS;
        $alias = $baseTable . '_Versions_Latest';
        $localisedAlias = $baseTable . '_Localised_Versions_Latest';

        $subSelect
            ->addInnerJoin(
                sprintf('%s', $localisedVersionTable),
                sprintf(
                    '"%1$s"."RecordID" = "%2$s"."RecordID" AND "%1$s"."Version" = "%2$s"."Version"',
                    $alias,
                    $localisedAlias
                ),
                $localisedAlias
            )
            ->addWhere([sprintf('"%s"."Locale"', $localisedAlias) => $locale]);
    }

    /**
     * Localise version cache populate
     * Extension point in @see Versioned::prepopulate_versionnumber_cache()
     *
     * @param array $versions
     * @param DataObject|string $class
     * @param string $stage
     * @param array|null $idList
     */
    public function updatePrePopulateVersionNumberCache(array $versions, $class, string $stage, ?array $idList): void
    {
        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $className = $class instanceof DataObject ? $class->ClassName : $class;
        $list = $this->getCurrentVersionNumbers($className, $stage, $idList);

        foreach ($list as $id => $version) {
            $this->setVersionCacheItem($baseClass, $stage, $locale, $id, $version);
        }

        if ($idList) {
            return;
        }

        $this->setVersionCacheItem($baseClass, $stage, $locale, static::CACHE_COMPLETE);
    }

    /**
     * Localise version lookup
     * Extension point in @see Versioned::get_versionnumber_by_stage()
     *
     * @param int|null $version
     * @param DataObject|string $class
     * @param string $stage
     * @param int $id
     * @param bool $cache
     */
    public function updateGetVersionNumberByStage(?int &$version, $class, string $stage, int $id, bool $cache): void
    {
        $locale = FluentState::singleton()->getLocale();

        if (!$locale) {
            return;
        }

        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $className = $class instanceof DataObject ? $class->ClassName : $class;

        if ($cache) {
            $localisedVersion = $this->getVersionCacheItem($baseClass, $stage, $locale, $id);

            if ($localisedVersion !== false) {
                $version = $localisedVersion;

                return;
            }
        }

        $list = $this->getCurrentVersionNumbers($className, $stage, [$id]);

        if (!$list) {
            $version = null;

            return;
        }

        $localisedVersion = array_shift($list);

        if ($cache) {
            $this->setVersionCacheItem($baseClass, $stage, $locale, $id, $localisedVersion);
        }

        $version = $localisedVersion;
    }

    /**
     * List versions for all objects in current locale (or a subset based on provided ids)
     *
     * @param string $class
     * @param string $stage
     * @param array|null $ids
     * @return array
     */
    protected function getCurrentVersionNumbers(string $class, string $stage, ?array $ids = null): array
    {
        $locale = FluentState::singleton()->getLocale();
        $schema = DataObject::getSchema();
        $baseClass = $schema->baseDataClass($class);
        $baseTable = $schema->tableName($baseClass);

        /** @var DataObject|FluentExtension $singleton */
        $singleton = DataObject::singleton($class);
        $versionedTable = $baseTable . static::SUFFIX_VERSIONS;
        $localisedTable = $singleton->getLocalisedTable($baseTable);
        $localisedVersionTable = $localisedTable . static::SUFFIX_VERSIONS;

        if ($stage === Versioned::LIVE) {
            $localisedTable .= static::SUFFIX_LIVE;
        }

        // note the following query gets called for each record in the site tree - so it is a possible performance issue
        // the core implementation is much simpler but does not handle versions across locales
        $liveSegment = $stage === Versioned::LIVE
            ? sprintf(' AND "%s"."WasPublished" = 1', $versionedTable)
            : '';

        $idSegment = $ids
            ? sprintf(' AND "BaseTable"."RecordID" IN (%s)', DB::placeholders($ids))
            : '';

        $sql = 'SELECT "BaseTable"."RecordID", MAX("%1$s"."Version") as "LatestVersion" FROM "%2$s" AS "BaseTable"'
            . ' INNER JOIN "%1$s" ON "BaseTable"."RecordID" = "%1$s"."RecordID" AND "%1$s"."Locale" = ?'
            . ' INNER JOIN "%3$s" ON "%3$s"."RecordID" = "%1$s"."RecordID" AND "%3$s"."Version" = "%1$s"."Version"'
            . ' WHERE "BaseTable"."Locale" = ?%4$s%5$s'
            . ' GROUP BY "BaseTable"."RecordID"';

        $query = sprintf(
            $sql,
            $localisedVersionTable,
            $localisedTable,
            $versionedTable,
            $liveSegment,
            $idSegment
        );

        $params = [$locale, $locale];
        $params = $ids ? array_merge($params, $ids) : $params;

        $results = DB::prepared_query($query, $params);
        $versions = [];

        foreach ($results as $result) {
            $id = (int) $result['RecordID'];
            $version = (int) $result['LatestVersion'];

            $versions[$id] = $version;
        }

        return $versions;
    }

    /**
     * @param string $class
     * @param string $stage
     * @param string $locale
     * @param int $id
     * @return int|bool|null
     */
    protected function getVersionCacheItem(string $class, string $stage, string $locale, int $id)
    {
        if (isset($this->versionsCache[$class][$stage][$locale][$id])) {
            return $this->versionsCache[$class][$stage][$locale][$id] ?: null;
        }

        if (isset($this->versionsCache[$class][$stage][$locale][static::CACHE_COMPLETE])) {
            // if the cache was marked as "complete" then we know the record is missing, just return null
            // this is used for treeview optimisation to avoid unnecessary re-requests for draft pages
            return null;
        }

        // special value indicating that cache lookup couldn't find anything and fresh lookup needs to be done
        return false;
    }

    /**
     * @param string $class
     * @param string $stage
     * @param string $locale
     * @param mixed $key
     * @param int|null $value
     */
    protected function setVersionCacheItem(string $class, string $stage, string $locale, $key, ?int $value = 0): void
    {
        if (!array_key_exists($class, $this->versionsCache)) {
            $this->versionsCache[$class] = [];
        }

        if (!array_key_exists($stage, $this->versionsCache[$class])) {
            $this->versionsCache[$class][$stage] = [];
        }

        if (!array_key_exists($locale, $this->versionsCache[$class][$stage])) {
            $this->versionsCache[$class][$stage][$locale] = [];
        }

        if ($key === static::CACHE_COMPLETE) {
            $this->versionsCache[$class][$stage][$locale][$key] = true;

            return;
        }

        // Internally store nulls as 0
        $this->versionsCache[$class][$stage][$locale][$key] = $value ?: 0;
    }
}
