<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * @property string $Title
 * @property string $Locale
 * @property string $URLSegment
 * @property bool $IsDefault
 * @method FallbackLocale FallbackLocales()
 * @method Locale Fallbacks()
 */
class Locale extends DataObject
{
    use CachableModel;

    private static $table_name = 'Fluent_Locale';

    private static $singular_name = 'Locale';

    private static $plural_name = 'Locales';

    private static $summary_fields = [
        'Title',
        'Locale',
        'URLSegment',
        'IsDefault',
    ];

    /**
     * @config
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(100)',
        'Locale' => 'Varchar(10)',
        'URLSegment' => 'Varchar(100)',
        'IsDefault' => 'Boolean',
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
                CheckboxField::create(
                    'IsDefault',
                    _t(__CLASS__.'.IS_DEFAULT', 'This is the default locale')
                )
                    ->setAttribute('data-hides', 'ParentDefaultID')
                    ->setDescription(_t(
                        __CLASS__.'.IS_DEFAULT_DESCRIPTION',
                        <<<DESC
    Note: Per-domain specific locale can be assigned on the Locales tab
    and will override this value for specific domains.
DESC
                    )),
                DropdownField::create(
                    'DomainID',
                    _t(__CLASS__.'.DOMAIN', 'Domain'),
                    Domain::get()->map('ID', 'Domain')
                )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)'))
            ]
        );

        // Add default selection
        $defaultField = GridField::create(
            'FallbackLocales',
            _t(__CLASS__.'.FALLBACKS', 'Fallback Locales'),
            $this->FallbackLocales(),
            GridFieldConfig_RelationEditor::create()
                ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
                ->addComponent(new GridFieldOrderableRows('Sort'))
        );
        $fields->addFieldToTab('Root.Fallbacks', $defaultField);

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
        return $locales->filter('IsDefault', 1)->first()
            ?: $locales->first();
    }

    /**
     * Get object by locale code
     *
     * @param string $locale
     * @return Locale
     */
    public static function getByLocale($locale)
    {
        if (!$locale) {
            return null;
        }
        return Locale::getCached()->filter('Locale', $locale)->first();
    }

    /**
     * Returns whether the given locale matches the current Locale object
     *
     * @param  string $locale E.g. en_NZ, en-NZ, en-nz-1990
     * @return bool
     */
    public function isLocale($locale)
    {
        return stripos(str_replace('_', '-', $locale), str_replace('_', '-', $this->Locale)) === 0;
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
        $locales = Locale::getCached();

        // Optionally filter by domain
        $domainObj = Domain::getByDomain($domain);
        if ($domainObj) {
            return $locales->filter('DomainID', $domainObj->ID);
        }

        return $locales;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // If this is the default locale, remove default from other locales
        if ($this->IsDefault) {
            $table = $this->baseTable();
            DB::prepared_query(
                "UPDATE \"{$table}\" SET \"IsDefault\" = 0 WHERE \"ID\" != ?",
                [ $this->ID ]
            );
        }
    }

    protected $chain = null;

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
}
