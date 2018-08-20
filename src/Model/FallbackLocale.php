<?php

namespace TractorCow\Fluent\Model;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;

/**
 * Class FallbackLocale
 *
 * @package TractorCow\Fluent\Model
 * @property int Sort
 * @method Locale Locale()
 * @method Locale Parent()
 */
class FallbackLocale extends DataObject
{
    private static $table_name = 'Fluent_FallbackLocale';

    private static $summary_fields = [
        'Locale.Title' => 'Locale',
    ];

    public function getTitle()
    {
        $locale = $this->Locale();
        if ($locale && $locale->exists()) {
            return $locale->getTitle();
        }
        return null;
    }

    private static $has_one = [
        'Parent' => Locale::class,
        'Locale' => Locale::class,
    ];

    private static $db = [
        'Sort' => 'Int',
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create(
            DropdownField::create(
                'LocaleID',
                _t(__CLASS__.'.LOCALE', 'Locale'),
                Locale::getCached()->map('ID', 'Title')
            )
        );
        $this->extend('updateCMSFields', $fields);
        return $fields;
    }
}
