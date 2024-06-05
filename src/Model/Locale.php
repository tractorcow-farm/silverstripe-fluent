<?php

namespace TractorCow\Fluent\Model;

use DateTime;
use DateTimeZone;
use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use TractorCow\Fluent\Control\LocaleAdmin;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\Extension\Traits\FluentObjectTrait;
use TractorCow\Fluent\State\FluentState;

/**
 * @property string $Title
 * @property string $Locale
 * @property string $URLSegment
 * @property bool $IsGlobalDefault
 * @property int $DomainID
 * @property bool $UseDefaultCode
 * @property string $Timezone
 * @method HasManyList<FallbackLocale> FallbackLocales()
 * @method ManyManyThroughList<Locale> Fallbacks()
 * @method Domain Domain() Raw SQL Domain (unfiltered by domain mode)
 */
class Locale extends DataObject implements PermissionProvider
{
    use CachableModel;

    /**
     * Code for accessing cross-locale actions
     */
    const CMS_ACCESS_MULTI_LOCALE = 'Fluent_Actions_MultiLocale';

    /**
     * Prefix for per-locale permission code.
     *
     * Note that this is not a permission code in itself, and must always be
     * joined with a locale.
     */
    const CMS_ACCESS_FLUENT_LOCALE = 'Fluent_Locale_';

    private static $table_name = 'Fluent_Locale';

    private static $singular_name = 'Locale';

    private static $plural_name = 'Locales';

    /**
     * hreflang for default landing pages.
     * Note: PHP's ext-intl doesn't support this code, so only use it
     * in templates.
     */
    const X_DEFAULT = 'x-default';

    private static $summary_fields = [
        'Title'           => 'Title',
        'Locale'          => 'Locale',
        'URLSegment'      => 'URL',
        'IsGlobalDefault' => 'Global Default',
        'Domain.Domain'   => 'Domain',
    ];

    /**
     * @config
     * @var array
     */
    private static $db = [
        'Title'           => 'Varchar(100)',
        'Locale'          => 'Varchar(10)',
        'URLSegment'      => 'Varchar(100)',
        'IsGlobalDefault' => 'Boolean',
        'UseDefaultCode'  => 'Boolean',
        'Sort'            => 'Int',
        'Timezone'        => 'Varchar(100)',
    ];

    private static $default_sort = '"Fluent_Locale"."Sort" ASC, "Fluent_Locale"."Locale" ASC';

    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->Timezone = date_default_timezone_get();
    }

    /**
     * @config
     * @var array
     */
    private static $has_one = [
        'Domain' => Domain::class,
    ];

    private static $has_many = [
        'FallbackLocales' => FallbackLocale::class . '.Parent',
    ];

    private static $many_many = [
        'Fallbacks' => [
            'through' => FallbackLocale::class,
            'from'    => 'Parent',
            'to'      => 'Locale',
        ],
    ];

    /**
     * @var ArrayList<Locale>
     */
    protected $chain = null;

    /**
     * @var Locale[]
     */
    protected static $locales_by_title;

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        // Migrate legacy permission codes to new codes
        $permissions = Permission::get()->filter('Code:StartsWith', 'CMS_ACCESS_Fluent_');
        $count = $permissions->count();

        if ($count) {
            DB::alteration_message(sprintf('Migrating %d old fluent permissions', $count), 'changed');
        }

        foreach ($permissions as $permission) {
            $permission->Code = str_replace('CMS_ACCESS_Fluent_', 'Fluent_', $permission->Code);
            $permission->write();
        }
    }

    /**
     * Get internal title for this locale
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getField('Title');
        if ($title) {
            return $title;
        }
        return $this->getDefaultTitle();
    }

    /**
     * Long title (including locale code)
     *
     * @return string
     */
    public function getLongTitle()
    {
        return "{$this->Title} ({$this->Locale})";
    }

    /**
     * @return string
     */
    protected function getDefaultTitle()
    {
        // Get default name from locale
        return i18n::getData()->localeName($this->getLocale());
    }

    /**
     * Locale code for this object
     *
     * @return string
     */
    public function getLocale()
    {
        $locale = $this->getField('Locale');
        if ($locale) {
            return $locale;
        }

        return $this->getDefaultLocale();
    }

    /**
     * Default locale for
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return i18n::config()->get('default_locale');
    }

    /**
     * Get the locale's country part
     *
     * @return string e.g. "NZ" for "en_NZ"
     */
    public function getLocaleSuffix()
    {
        $bits = explode('_', $this->Locale);
        return array_pop($bits);
    }

    /**
     * Returns the label to display for Fluent badges in the CMS. By default this is the
     * locale's URLSegment as set in /admin/locales, but can be configured with extensions.
     *
     * For example, you may want to display the full locale badge:
     * <code>
     * public function updateBadgeLabel(&$badgeLabel)
     * {
     *     $badgeLabel = $this->owner->Locale;
     * }
     * </code>
     *
     * @return string
     */
    public function getBadgeLabel()
    {
        $badgeLabel = $this->getURLSegment();
        $this->extend('updateBadgeLabel', $badgeLabel);
        return (string)$badgeLabel;
    }

    /**
     * RFC 1766 hreflang
     *
     * @return string
     */
    public function getHrefLang()
    {
        if ($this->UseDefaultCode) {
            return Locale::X_DEFAULT;
        }
        return strtolower(i18n::convert_rfc1766($this->Locale));
    }

    /**
     * Get URLSegment for this locale
     *
     * @return string
     */
    public function getURLSegment()
    {
        $segment = $this->getField('URLSegment');
        if ($segment) {
            return $segment;
        }

        // Default to locale
        return $this->getLocale();
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));

        // Main tab
        $fields->addFieldsToTab(
            'Root.Main',
            [
                DropdownField::create(
                    'Locale',
                    _t(__CLASS__ . '.LOCALE', 'Locale'),
                    i18n::getData()->getLocales()
                ),
                TextField::create(
                    'Title',
                    _t(__CLASS__ . '.LOCALE_TITLE', 'Title')
                )->setAttribute('placeholder', $this->getDefaultTitle()),
                TextField::create(
                    'URLSegment',
                    _t(__CLASS__ . '.LOCALE_URL', 'URL Segment')
                )->setAttribute('placeholder', $this->Locale),
                $globalDefault = CheckboxField::create(
                    'IsGlobalDefault',
                    _t(__CLASS__ . '.IS_DEFAULT', 'This is the global default locale')
                )
                    ->setAttribute('data-hides', 'ParentDefaultID')
                    ->setDescription(_t(
                        __CLASS__ . '.IS_DEFAULT_DESCRIPTION',
                        'Note: Per-domain specific locale can be assigned on the Locales tab'
                        . ' and will override this value for specific domains.'
                    )),
                CheckboxField::create(
                    'UseDefaultCode',
                    _t(__CLASS__ . '.USE_X_DEFAULT', 'Use {code} as SEO language code (treat as global)', ['code' => Locale::X_DEFAULT])
                )
                    ->setDescription(_t(
                        __CLASS__ . '.USE_X_DEFAULT_DESCRIPTION',
                        'Use of this code indicates to search engines that this is a non-localised global landing page'
                    )),
                DropdownField::create(
                    'Timezone',
                    _t(__CLASS__ . '.TIMEZONE', 'Timezone'),
                    $this->getTimezones()
                )->setEmptyString(_t(__CLASS__ . '.DEFAULT_NONE', '(none)')),
                DropdownField::create(
                    'DomainID',
                    _t(__CLASS__ . '.DOMAIN', 'Domain'),
                    Domain::get()->map('ID', 'Domain')
                )->setEmptyString(_t(__CLASS__ . '.DEFAULT_NONE', '(none)'))
            ]
        );

        if ($this->exists()) {
            $config = GridFieldConfig::create()
                ->addComponents(
                    new GridFieldButtonRow(),
                    new GridFieldAddNewInlineButton(),
                    new GridFieldOrderableRows('Sort'),
                    $editable = new GridFieldEditableColumns(),
                    new GridFieldDeleteAction()
                );

            $editable->setDisplayFields([
                'LocaleID' => function () {
                    return DropdownField::create(
                        'LocaleID',
                        _t(__CLASS__ . '.LOCALE', 'Locale'),
                        Locale::getCached()->exclude('Locale', $this->Locale)->map('ID', 'Title')
                    );
                }
            ]);

            // Add default selection
            $defaultField = GridField::create(
                'FallbackLocales',
                _t(__CLASS__ . '.FALLBACKS', 'Fallback Locales'),
                $this->FallbackLocales(),
                $config
            );
            $fields->addFieldToTab('Root.Fallbacks', $defaultField);
        } else {
            $fields->addFieldToTab(
                'Root.Fallbacks',
                LiteralField::create(
                    'UnsavedNotice',
                    '<p>' . _t(__CLASS__ . '.UnsavedNotice', "You can add fallbacks once you've saved the locale.")
                )
            );

            // If this is the first locale, it should be checked by default
            if (static::getCached()->count() === 0) {
                $globalDefault->setValue(true);
            }
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Get default locale
     *
     * @param string|null|true $domain If provided, the default locale for the given domain will be returned.
     *                                 If true, then the current state domain will be used (if in domain mode).
     * @return Locale
     */
    public static function getDefault($domain = null)
    {
        // Get by domain if it exists and has a default
        $domainObject = Domain::getByDomain($domain);
        if ($domainObject) {
            $default = $domainObject->DefaultLocale();
            if ($default) {
                return $default;
            }
        }

        // Get explicit or implicit default
        $locales = static::getLocales();
        return $locales->filter('IsGlobalDefault', 1)->first()
            ?: $locales->first();
    }

    /**
     * Get current locale object
     *
     * @return Locale|null
     */
    public static function getCurrentLocale(): ?Locale
    {
        $locale = FluentState::singleton()->getLocale();

        return static::getByLocale($locale);
    }

    /**
     * Get object by locale code.
     *
     * @param string|Locale $locale
     * @return Locale|null
     */
    public static function getByLocale($locale): ?Locale
    {
        if (!$locale) {
            return null;
        }

        if ($locale instanceof Locale) {
            return $locale;
        }

        // Get filtered locale
        return Locale::getCached()->find('Locale', $locale);
    }

    /**
     * Returns whether the given locale matches the current Locale object
     *
     * @param string $locale E.g. en_NZ, en-NZ, en-nz-1990
     * @return bool
     */
    public function isLocale($locale)
    {
        return stripos(i18n::convert_rfc1766($locale), i18n::convert_rfc1766($this->Locale)) === 0;
    }

    /**
     * Check if this is the default (non-global).
     * Use IsGlobalDefault check if global default otherwise.
     *
     * @return bool
     */
    public function getIsDefault()
    {
        // Get default for own domain
        $default = static::getDefault($this->getDomain());

        // Compare best default with current locale
        return $default && ((int)$default->ID === (int)$this->ID);
    }

    /**
     * Get domain if in domain mode
     *
     * @return Domain|null Domain found, or null if not in domain mode (or no domain)
     */
    public function getDomain()
    {
        if (FluentState::singleton()->getIsDomainMode() && $this->DomainID) {
            return Domain::getCached()->byID($this->DomainID);
        }
        return null;
    }

    /**
     * Determine if this locale is the sole locale on its domain,
     * or globally if domain mode is disabled
     *
     * @return bool
     */
    public function getIsOnlyLocale()
    {
        // Get locales filtered by same domain (in domain mode)
        $locales = $this->getSiblingLocales();
        return $locales->count() < 2;
    }

    /**
     * Get available locales
     *
     * @param string|null|true $domain If provided, locales for the given domain will be returned.
     *                                 If true, then the current state domain will be used (if in domain mode).
     * @return ArrayList<Locale>
     */
    public static function getLocales($domain = null)
    {
        // Optionally filter by domain
        $domainObj = Domain::getByDomain($domain);
        if ($domainObj) {
            return $domainObj->getLocales();
        }

        return Locale::getCached();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // If this is the default locale, remove default from other locales
        if ($this->IsGlobalDefault) {
            $table = $this->baseTable();
            DB::prepared_query(
                "UPDATE \"{$table}\" SET \"IsGlobalDefault\" = 0 WHERE \"ID\" != ?",
                [$this->ID]
            );
        }
    }

    /**
     * Get chain of all locales that should be preferred when this locale is current
     *
     * @return ArrayList<Locale>
     */
    public function getChain()
    {
        if ($this->chain) {
            return $this->chain;
        }

        $this->chain = ArrayList::create();

        // Push the current locale as the first fallback.
        $this->chain->push($this);

        // Get the current locale and sort them by "Sort" field.
        $fallbacks = $this->FallbackLocales()->sort('Sort');
        foreach ($fallbacks as $fallback) {
            $this->chain->push($fallback->Locale());
        }

        return $this->chain;
    }

    /**
     * Fetch a native language string from the {@link i18n} class via the current locale code in the format "XX_xx". In
     * the event a match cannot be found in any framework resource, an empty string is returned.
     *
     * @return string The native language string for the current locale e.g. "portugu&ecirc;s (Brazil)"
     */
    public function getNativeName()
    {
        $locales = i18n::getData();

        // Attempts to fetch the native language string via the `i18n::$common_languages` array
        if ($native = $locales->languageName($locales->langFromLocale($this->Locale))) {
            return $native;
        }

        return '';
    }

    /**
     * Determine the base URL within the current locale
     *
     * @return string
     */
    public function getBaseURL()
    {
        $base = Director::baseURL();

        // Prepend hostname for domain mode
        $domain = $this->getDomain();
        if ($domain) {
            $base = Controller::join_links($domain->Link(), $base);
        }

        // Determine if base suffix should be appended
        $append = true;
        if ($this->getIsDefault()) {
            // Apply config
            $append = !(bool)Config::inst()->get(FluentDirectorExtension::class, 'disable_default_prefix');
        }

        if ($append) {
            // Append locale url segment
            $base = Controller::join_links($base, $this->getURLSegment(), '/');
        }

        return $base;
    }

    /**
     * Get other locales that appear alongside this (including self)
     *
     * @return ArrayList<Locale>
     */
    public function getSiblingLocales()
    {
        $domain = $this->getDomain();
        $locales = $domain
            ? $domain->getLocales()
            : Locale::getCached();
        return $locales;
    }

    /**
     * Get details for the current object in this locale.
     *
     * @return null|RecordLocale
     * @see FluentObjectTrait::LinkedLocales()
     */
    public function RecordLocale()
    {
        $recordID = $this->getSourceQueryParam('FluentObjectID');
        $recordClass = $this->getSourceQueryParam('FluentObjectClass');
        if (!$recordID || !$recordClass) {
            return null;
        }

        $record = DataObject::get($recordClass)->byID($recordID);

        if ($record) {
            return RecordLocale::create($record, $this);
        }

        return null;
    }

    /**
     * Get permission code to enable access in this locale
     *
     * @return string
     */
    public function getLocaleEditPermission()
    {
        $prefix = Locale::CMS_ACCESS_FLUENT_LOCALE;
        return "{$prefix}{$this->Locale}";
    }


    public function providePermissions()
    {
        $category = _t(__CLASS__ . '.PERMISSION', 'Localisation');
        $permissions = [
            // @todo - Actually implement this check on those actions
            Locale::CMS_ACCESS_MULTI_LOCALE => [
                'name'     => _t(
                    __CLASS__ . '.MULTI_LOCALE',
                    'Access to multi-locale actions (E.g. save in all locales)'
                ),
                'category' => $category,
            ],
        ];
        foreach (Locale::getCached() as $locale) {
            $permissions[$locale->getLocaleEditPermission()] = [
                'name'     => _t(
                    __CLASS__ . '.EDIT_LOCALE',
                    'Access "{title}" ({locale})',
                    [
                        'title'  => $locale->Title,
                        'locale' => $locale->Locale,
                    ]
                ),
                'category' => $category,
            ];
        }
        return $permissions;
    }


    /**
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        // Access locale admin permission
        return LocaleAdmin::singleton()->canView($member);
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        // Access locale admin permission
        return LocaleAdmin::singleton()->canView($member);
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ($extended !== null) {
            return $extended;
        }

        // Access locale admin permission
        return LocaleAdmin::singleton()->canView($member);
    }

    /**
     * Get list of timezones
     *
     * @return array
     * @throws Exception
     */
    protected function getTimezones()
    {
        static $timezones = null;
        if ($timezones !== null) {
            return $timezones;
        }

        $timezones = [];
        $offsets = [];
        $now = new DateTime('now', new DateTimeZone('UTC'));

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $now->setTimezone(new DateTimeZone($timezone));
            $offsets[] = $offset = $now->getOffset();

            // Format offset
            $hours = intval($offset / 3600);
            $minutes = abs(intval($offset % 3600 / 60));
            $name = str_replace(['/', '_', 'St'], [', ', ' ', 'St. '], $timezone);
            $offsetTime = $offset ? sprintf('%+03d:%02d', $hours, $minutes) : '';
            $timezones[$timezone] = "(GMT{$offsetTime}) {$name}";
        }

        array_multisort($offsets, $timezones);

        return $timezones;
    }
}
