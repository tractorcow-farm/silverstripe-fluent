<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Locale
 * @property string $Alias
 * @property bool $IsDefault
 * @method Locale Default()
 */
class Locale extends DataObject
{
    private static $table_name = 'Locale';

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
        'Locale' => 'Varchar(10)',
        'Title' => 'Varchar(100)',
        'URLSegment' => 'Varchar(100)',
        'IsDefault' => 'Boolean',
    ];

    /**
     * @config
     * @var array
     */
    private static $has_one = [
        'Default' => Locale::class,
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
                'DefaultID',
                _t(__CLASS__.'.DEFAULT', 'Fallback locale'),
                Locale::get()->map('ID', 'Title')
            )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)')),
            CheckboxField::create(
                'IsDefault',
                _t(__CLASS__.'.IS_DEFAULT', 'This is the default locale')
            )
                ->setAttribute('data-hides', 'DefaultID')
                ->setDescription(_t(
                __CLASS__.'.IS_DEFAULT_DESCRIPTION',
<<<DESC
Note: Default locale cannot have a fallback.
Switching to a new default will copy content from the old locale to the new one.
DESC
            ))
        );
    }
}
