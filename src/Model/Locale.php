<?php

namespace TractorCow\Fluent\Model;

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
        if ($this->Locale) {
            return $this->Locale;
        }
        return null;
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
            ),
            DropdownField::create(
                'DefaultID',
                _t(__CLASS__.'.DEFAULT', 'Fallback locale'),
                Locale::get()->map('ID', 'LocaleName')
            )->setEmptyString(_t(__CLASS__.'.DEFAULT_NONE', '(none)'))
        );
    }

    /**
     * @return null|string
     */
    protected function getDefaultTitle()
    {
        if ($this->Locale) {
            return i18n::getData()->localeName($this->Locale);
        }
        return null;
    }
}
