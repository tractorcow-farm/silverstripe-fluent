<?php

namespace TractorCow\Fluent\Model;

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
use SilverStripe\ORM\ManyManyList;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use TractorCow\Fluent\Extension\FluentDirectorExtension;
use TractorCow\Fluent\State\FluentState;

/**
 * @property string $Title
 * @property string $Locale
 * @property string $URLSegment
 * @property bool $IsGlobalDefault
 * @property int $DomainID
 * @method HasManyList|FallbackLocale[] FallbackLocales()
 * @method ManyManyList|Locale[] Fallbacks()
 * @method Domain Domain() Raw SQL Domain (unfiltered by domain mode)
 */
class Locale extends DataObject
{
    use CachableModel;

    private static $table_name = 'Fluent_Locale';

    private static $singular_name = 'Locale';

    private static $plural_name = 'Locales';

    private static $summary_fields = [
        'Title' => 'Title',
        'Locale' => 'Locale',
        'URLSegment' => 'URL',
        'IsGlobalDefault' => 'Global Default',
        'Domain.Domain' => 'Domain',
    ];

    /**
     * @config
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(100)',
        'Locale' => 'Varchar(10)',
        'URLSegment' => 'Varchar(100)',
        'IsGlobalDefault' => 'Boolean',
    ];

    private static $default_sort = '"Fluent_Locale"."Locale" ASC';

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
            'from' => 'Parent',
            'to' => 'Locale',
        ],
    ];

    /**
     * @var ArrayList
     */
    protected $chain = null;

    /**
     * @var Locale[]
     */
    protected static $locales_by_title;

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
     * Get a short segment of the locale code for display in things like badges
     *
     * @return string e.g. "NZ" for "en_NZ"
     */
    public function getLocaleSuffix()
    {
        $bits = explode('_', $this->Locale);
        return array_pop($bits);
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
                    _t(__CLASS__.'.LOCALE', 'Locale'),
                    i18n::getData()->getLocales()
                ),
                TextField::create(
                    'Title',
                    _t(__CLASS__.'.LOCALE_TITLE', 'Title')
                )->setAttribute('placeholder', $this->getDefaultTitle()),
                TextField::create(
                    'URLSegment',
                    _t(__CLASS__.'.LOCALE_URL', 'URL Segment')
                )->setAttribute('placeholder', $this->Locale),
                $globalDefault = CheckboxField::create(
                    'IsGlobalDefault',
                    _t(__CLASS__.'.IS_DEFAULT', 'This is the global default locale')
                )
                    ->setAttribute('data-hides', 'ParentDefaultID')
                    ->setDescription(_t(
                        __CLASS__.'.IS_DEFAULT_DESCRIPTION',
                        'Note: Per-domain specific locale can be assigned on the Locales tab'
                        . ' and will override this value for specific domains.'
                    )),
                DropdownField::create(
                    'DomainID',
                    _t(__CLASS__.'.DOMAIN', 'Domain'),
                    Domain::get()->map('ID', 'Domain')
                )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)'))
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
                        _t(__CLASS__.'.LOCALE', 'Locale'),
                        Locale::getCached()->exclude('Locale', $this->Locale)->map('ID', 'Title')
                    );
                }
            ]);

            // Add default selection
            $defaultField = GridField::create(
                'FallbackLocales',
                _t(__CLASS__.'.FALLBACKS', 'Fallback Locales'),
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
     * If true, then the current state domain will be used (if in domain mode).
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
     * @return Locale
     */
    public static function getCurrentLocale()
    {
        $locale = FluentState::singleton()->getLocale();
        return static::getByLocale($locale);
    }

    /**
     * Get object by locale code.
     *
     * @param string|Locale $locale
     * @return Locale
     */
    public static function getByLocale($locale)
    {
        if (!$locale) {
            return null;
        }

        if ($locale instanceof Locale) {
            return $locale;
        }

        if (!static::$locales_by_title) {
            static::$locales_by_title = [];
            foreach (Locale::getCached() as $localeObj) {
                static::$locales_by_title[$localeObj->Locale] = $localeObj;
            }
        }

        // Get filtered locale
        return isset(static::$locales_by_title[$locale]) ? static::$locales_by_title[$locale] : null;
    }

    /**
     * Returns whether the given locale matches the current Locale object
     *
     * @param  string $locale E.g. en_NZ, en-NZ, en-nz-1990
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
     * If true, then the current state domain will be used (if in domain mode).
     * @return ArrayList
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
                [ $this->ID ]
            );
        }
    }

    /**
     * Get chain of all locales that should be preferred when this locale is current
     *
     * @return ArrayList
     */
    public function getChain()
    {
        if ($this->chain) {
            return $this->chain;
        }

        // Build list
        $this->chain = ArrayList::create();
        $this->chain->push($this);
        $this->chain->merge($this->Fallbacks());

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
            $append = !(bool) Config::inst()->get(FluentDirectorExtension::class, 'disable_default_prefix');
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
     * @return ArrayList
     */
    public function getSiblingLocales()
    {
        $domain = $this->getDomain();
        $locales = $domain
            ? $domain->getLocales()
            : Locale::getCached();
        return $locales;
    }
}
