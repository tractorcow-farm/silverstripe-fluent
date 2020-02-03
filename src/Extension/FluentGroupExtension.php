<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Group;
use TractorCow\Fluent\Model\Locale;

/**
 * Provides fluent-filtered permission control for CMS users
 *
 * @method ManyManyList|Locale[] EnabledLocales()
 * @property Group|FluentGroupExtension $owner
 */
class FluentGroupExtension extends DataExtension
{
    private static $many_many = [
        'EnabledLocales' => Locale::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $locales = Locale::get();
        if ($locales->count() === 0) {
            return;
        }

        // Checklist list of visible locales
        $checkboxSetField = CheckboxSetField::create(
            'EnabledLocales',
            _t(
                __CLASS__ . '.ENABLED_LOCALES',
                'Enabled in the following locales'
            ),
            $locales
        )->setDescription(_t(
            __CLASS__ . '.ENABLED_LOCALES_DESCRIPTION',
            'Note: Leaving this field empty enables this group in all locales.'
        ));

        $fields->addFieldToTab('Root.Permissions', $checkboxSetField, 'Permissions');
    }
}
