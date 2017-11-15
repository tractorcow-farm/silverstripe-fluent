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
 * Class FluentFilteredExtension
 * @package TractorCow\Fluent\Extension
 *
 * @property FluentSiteTreeExtension|DataObject $owner
 * @method DataList|Locale[] FilteredLocales()
 */
class FluentFilteredExtension extends DataExtension
{
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

        $fields->insertAfter(new Tab('Locales'), 'Main');

        $config = GridFieldConfig_RelationEditor::create();
        $config->removeComponentsByType(GridFieldAddNewButton::class);

        // create a GridField to manage what Locales this Page can be displayed in.
        $fields->addFieldToTab(
            'Root.Locales',
            GridField::create(
                'FilteredLocales',
                _t(__CLASS__.'.FILTERED_LOCALES', 'Display in the following Locales'),
                $this->owner->FilteredLocales(),
                $config
            )
        );
    }

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

        $schema = DataObject::getSchema();
        $table = $schema->baseDataTable(get_class($this->owner));
        $filteredLocalesTable = $table . '_FilteredLocales';

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
