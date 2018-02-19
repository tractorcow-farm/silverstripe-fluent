<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * @property FluentFilteredExtension|DataObject $owner
 * @method DataList|Locale[] FilteredLocales()
 */
class FluentFilteredExtension extends DataExtension
{
    /**
     * The table suffix that will be applied to a DataObject's base table.
     */
    const SUFFIX = 'FilteredLocales';

    private static $many_many = [
        'FilteredLocales' => Locale::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $locales = Locale::get();

        // If there are no Locales, then we're not adding any fields.
        if ($locales->count() === 0) {
            return;
        }

        $fields->insertAfter('Main', new Tab('Locales'));

        $config = GridFieldConfig_RelationEditor::create();
        $config->removeComponentsByType(GridFieldAddNewButton::class);

        // create a GridField to manage what Locales this Page can be displayed in.
        $fields->addFieldToTab(
            'Root.Locales',
            GridField::create(
                'FilteredLocales',
                _t(__CLASS__.'.FILTERED_LOCALES', 'Display in the following locales'),
                $this->owner->FilteredLocales(),
                $config
            )
        );
    }

    /**
     * @param Locale|null $locale
     * @return bool
     */
    public function isAvailableInLocale(Locale $locale = null)
    {
        if ($locale === null) {
            $locale = Locale::getCurrentLocale();
        }

        $locales = $this->owner->FilteredLocales()->filter([
            $locale->baseTable() . 'ID' => $locale->ID,
        ]);

        return $locales->count() === 1;
    }

    /**
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        // We don't want this logic applied in the CMS.
        if (!FluentState::singleton()->getIsFrontend()) {
            return;
        }

        $locale = $this->getDataQueryLocale($dataQuery);
        if (!$locale) {
            return;
        }

        $table = $this->owner->baseTable();
        $filteredLocalesTable = $table . '_' . self::SUFFIX;

        $query->addInnerJoin(
            $filteredLocalesTable,
            "\"{$filteredLocalesTable}\".\"{$table}ID\" = \"{$table}\".\"ID\" AND \"{$filteredLocalesTable}\".\"{$locale->baseTable()}ID\" = ?",
            null,
            20,
            [
                $locale->ID,
            ]
        );
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
}
