<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\State\LocaleDetector;

/**
 * @property string $Locale
 * @property string $Alias
 * @property bool $IsDefault
 * @property int $ParentDefaultID
 * @method Locale ParentDefault()
 */
class Locale extends DataObject
{
    use CachableModel;

    private static $table_name = 'Locale';

    private static $singular_name = 'Locale';

    private static $plural_name = 'Locales';

    private static $summary_fields = [
        'Title',
        'Locale',
        'URLSegment',
        'IsDefault',
    ];

    private static $default_sort = '"Locale"."Locale" ASC';

    /**
     * @config
     * @var array
     */
    private static $db = [
        'Locale' => 'Varchar(10)',
        'Title' => 'Varchar(100)',
        'Alias' => 'Varchar(20)',
        'URLSegment' => 'Varchar(100)',
        'IsDefault' => 'Boolean',
    ];

    /**
     * @config
     * @var array
     */
    private static $has_one = [
        'ParentDefault' => Locale::class,
        'Domain' => Domain::class,
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
        return FieldList::create(
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
            DropdownField::create(
                'ParentDefaultID',
                _t(__CLASS__.'.DEFAULT', 'Fallback locale'),
                Locale::get()->map('ID', 'Title')
            )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)')),
            CheckboxField::create(
                'IsDefault',
                _t(__CLASS__.'.IS_DEFAULT', 'This is the default locale')
            )
                ->setAttribute('data-hides', 'ParentDefaultID')
                ->setDescription(_t(
                __CLASS__.'.IS_DEFAULT_DESCRIPTION',
<<<DESC
Note: Default locale cannot have a fallback.
Switching to a new default will copy content from the old locale to the new one.
DESC
            )),
            DropdownField::create(
                'DomainID',
                _t(__CLASS__.'.DOMAIN', 'Domain'),
                Domain::get()->map('ID', 'Domain')
            )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)'))
        );
    }

    /**
     * Get default locale
     *
     * @param  string|null $domain If provided, the default locale for the given domain will be returned
     * @return Locale
     */
    public static function getDefault($domain = null)
    {
        /** @var \SilverStripe\ORM\ArrayList $locales */
        $locales = Locale::getCached();

        // Optionally filter by domain
        if ($domain) {
            $domain = Domain::getCached()->filter('Domain', $domain)->first();
            if ($domain && $domain->exists()) {
                $locales = $domain->Locales();
            }
        }

        // If no default specified, treat first locale as default
        return $locales->filter('IsDefault', 1)->first()
            ?: $locales->first();
    }

    /**
     * Get default for this locale
     *
     * @return Locale
     */
    public function getParent()
    {
        $id = $this->ParentDefaultID;
        return Locale::getCached()->byID($id);
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
}
