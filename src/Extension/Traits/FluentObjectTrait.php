<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Admin\CMSEditLinkExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\FieldType\DBField;
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
    abstract protected function updateLocalisationTabColumns(&$summaryColumns);

    /**
     * Add additional configs to localisation table
     *
     * @param GridFieldConfig $config
     */
    abstract protected function updateLocalisationTabConfig(
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
    protected function augmentDataQueryCreation(
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
        /** @var DataObject|CMSEditLinkExtension $owner */
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
        // Remove filters as the displayed data is in ArrayList format
        $config->removeComponentsByType(GridFieldFilterHeader::class);

        $columns = $config->getComponentByType(GridFieldDataColumns::class);
        $summaryColumns = [
            'Title' => 'Title',
            'Locale' => 'Locale'
        ];

        // Augment Localisation tab with clickable locale links to allow easy navigation between model localisations
        if ($owner->hasExtension(CMSEditLinkExtension::class)) {
            $controller = Controller::has_curr() ? Controller::curr() : null;
            $request = $controller?->getRequest();

            // Pass getVars separately so we can process them later
            $params = $request?->getVars() ?? [];

            // This is to get URL only, getVars are not part of the URL
            $url = $owner->CMSEditLink();
            $url = Director::makeRelative($url);

            if ($url) {
                $summaryColumns['Title'] = [
                    'title' => 'Title',
                    'callback' => function (Locale $object) use ($url, $params): ?DBField {
                        if (!$object->RecordLocale()) {
                            return null;
                        }

                        $recordLocale = $object->RecordLocale();
                        $locale = $recordLocale->getLocale();
                        $params['l'] = $locale;
                        $localeLink = Controller::join_links($url, '?' . http_build_query($params));
                        $localeTitle = Convert::raw2xml($recordLocale->getTitle());
                        $render = sprintf('<a href="%s" target="_top">%s</a>', $localeLink, $localeTitle);

                        return DBField::create_field('HTMLVarchar', $render);
                    }
                ];
            }
        }

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
                ->setTitle(_t('TractorCow\Fluent\Extension\Traits\FluentObjectTrait.TAB_LOCALISATION', 'Localisation'));
        } else {
            $fields->push($gridField);
        }
    }
}
