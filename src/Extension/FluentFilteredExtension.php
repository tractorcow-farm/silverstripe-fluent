<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\Traits\FluentObjectTrait;
use TractorCow\Fluent\Model\Delete\UsesDeletePolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * @property FluentFilteredExtension|DataObject $owner
 * @method DataList|Locale[] FilteredLocales()
 */
class FluentFilteredExtension extends DataExtension
{
    /**
     * Deletions are managed via DeletePolicy
     */
    use UsesDeletePolicy;
    use FluentObjectTrait;

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

        // create a GridField to manage what Locales this Page can be displayed in.
        $fields->addFieldToTab(
            'Root.FilteredLocales',
            CheckboxSetField::create(
                'FilteredLocales',
                _t(__CLASS__ . '.FILTERED_LOCALES', 'Display in the following locales'),
                $locales
            )
        );

        $fields
            ->fieldByName('Root.FilteredLocales')
            ->setTitle(_t(__CLASS__ . '.TAB_FILTERED_LOCALES', 'Filtered Locales'));

        // Add other fields
        $this->updateFluentCMSFields($fields);
    }

    /**
     * This method is only called if the Extension has been applied to SiteTree. If you are using this Extension on
     * other DataObjects you will need to implement your own Extension or method on that DataObject for flagging the
     * "filtered" state.
     *
     * @param array $flags
     */
    public function updateStatusFlags(&$flags)
    {
        // If there is no current FluentState, then we shouldn't update.
        if (!FluentState::singleton()->getLocale()) {
            return;
        }

        // No need to update flags if the Page is available in this Locale.
        if ($this->isAvailableInLocale()) {
            return;
        }

        // Add new status flag for "not visible".
        $flags['fluentfiltered'] = [
            'text'  => null,
            'title' => _t(__CLASS__ . '.LOCALEFILTEREDHELP', 'This page is not visible in this locale')
        ];
    }

    /**
     * @param string|Locale|null $locale
     * @return bool
     */
    public function isAvailableInLocale($locale = null)
    {
        if ($locale instanceof Locale) {
            $localeObject = $locale;
        } elseif ($locale) {
            $localeObject = Locale::getByLocale($locale);
        } else {
            $localeObject = Locale::getCurrentLocale();
        }
        if (!$localeObject) {
            return false;
        }

        $locales = $this->owner->FilteredLocales()->filter([
            $localeObject->baseTable() . 'ID' => $localeObject->ID,
        ]);

        return $locales->count() === 1;
    }

    /**
     * @param SQLSelect      $query
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

        // Dev has requested that we do not apply Filtered Locales when reading mode is set to DRAFT.
        if (!$this->owner->config()->get('apply_filtered_locales_to_stage') && $this->getModeIsStage()) {
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

    /**
     * There are two different DRAFT modes. One when browsing stage, and one when browsing archive. Both modes have
     * "Stage" at the very end of their reading_mode name.
     *
     * @return bool
     */
    protected function getModeIsStage()
    {
        $readingMode = Versioned::get_reading_mode();
        $draft = Versioned::DRAFT;

        if (strlen($readingMode) === 0) {
            $readingMode = Versioned::DEFAULT_MODE;
        }

        return substr_compare($readingMode, $draft, strlen($readingMode) - strlen($draft), strlen($draft)) === 0;
    }

    /**
     * @see FluentObjectTrait::updateFluentCMSFields()
     * @param $summaryColumns
     */
    public function updateLocalisationTabColumns(&$summaryColumns)
    {
        $summaryColumns['IsVisible'] = [
            'title'    => 'Visible',
            'callback' => function (Locale $object) {
                return $object->RecordLocale()->IsVisible() ? 'visible' : 'hidden';
            }
        ];
    }
}
