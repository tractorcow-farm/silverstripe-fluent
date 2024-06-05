<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Shared functionality between both FluentExtension and FluentFilteredExtension
 */
trait FluentObjectTrait
{
    /**
     * Add additional columns to localisation table
     *
     * @param $summaryColumns
     * @see self::updateFluentCMSFields()
     */
    abstract public function updateLocalisationTabColumns(&$summaryColumns);

    /**
     * Add additional configs to localisation table
     *
     * @param GridFieldConfig $config
     */
    abstract public function updateLocalisationTabConfig(
        GridFieldConfig $config
    );

    /**
     * Gets list of all Locale dataobjects, linked to this record
     *
     * @return ArrayList<Locale>
     * @see Locale::RecordLocale()
     */
    public function LinkedLocales()
    {
        if (!$this->owner->ID) {
            return null;
        }

        $locales = ArrayList::create();
        Locale::getCached()->each(function (DataObject $item) use ($locales) {
            // Create a clone of the locale model as we are going to add some context specific information into it
            // We don't want this information to be present in the globally shared locale cache
            $clone = clone $item;
            $clone->setSourceQueryParams([
                'FluentObjectID' => $this->owner->ID,
                'FluentObjectClass' => get_class($this->owner)
            ]);

            $locales->push($clone);
        });

        return $locales;
    }

    /**
     * Amend freshly created DataQuery objects with the current locale and frontend status
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentDataQueryCreation(
        SQLSelect $query,
        DataQuery $dataQuery
    ) {
        $state = FluentState::singleton();
        $dataQuery
            ->setQueryParam('Fluent.Locale', $state->getLocale())
            ->setQueryParam('Fluent.IsFrontend', $state->getIsFrontend());
    }

    /**
     * Update CMS fields for fluent objects.
     * These fields are added in addition to those added by specific extensions
     *
     * @param FieldList $fields
     */
    protected function updateFluentCMSFields(FieldList $fields)
    {
        /** @var DataObject $owner */
        $owner = $this->owner;
        if (!$owner->ID) {
            return;
        }

        // Avoid adding gridfield twice
        if ($fields->dataFieldByName('RecordLocales')) {
            return;
        }

        // Generate gridfield for handling localisations
        $config = GridFieldConfig_Base::create();

        $columns = $config->getComponentByType(GridFieldDataColumns::class);
        $summaryColumns = [
            'Title' => 'Title',
            'Locale' => 'Locale'
        ];

        // Let extensions override columns
        $owner->extend('updateLocalisationTabColumns', $summaryColumns);
        $columns->setDisplayFields($summaryColumns);

        // Let extensions override components
        $owner->extend('updateLocalisationTabConfig', $config);

        // Add gridfield to tab / fields
        $gridField = GridField::create(
            'RecordLocales',
            'Locales',
            $this->LinkedLocales(),
            $config
        );
        if ($fields->hasTabSet()) {
            $fields->addFieldToTab('Root.Locales', $gridField);

            $fields
                ->fieldByName('Root.Locales')
                ->setTitle(_t(__TRAIT__ . '.TAB_LOCALISATION', 'Localisation'));
        } else {
            $fields->push($gridField);
        }
    }
}
