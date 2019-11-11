<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Forms\GroupActionMenu;
use TractorCow\Fluent\Forms\PublishAction;
use TractorCow\Fluent\Forms\UnpublishAction;
use TractorCow\Fluent\Model\Locale;

/**
 * Shared functionality between both FluentExtension and FluentFilteredExtension
 *
 * @property DataObject $owner
 */
trait FluentObjectTrait
{
    /**
     * Gets list of all Locale dataobjects, linked to this record
     *
     * @see Locale::getRecordLocale()
     * @return DataList|Locale[]
     */
    public function LinkedLocales()
    {
        if (!$this->owner->ID) {
            return null;
        }

        return Locale::get()
            ->setDataQueryParam([
                'FluentObjectID'    => $this->owner->ID,
                'FluentObjectClass' => get_class($this->owner),
            ]);
    }


    /**
     * Update CMS fields for fluent objects.
     * These fields are added in addition to those added by specific extensions
     *
     * @param FieldList $fields
     */
    protected function updateFluentCMSFields(FieldList $fields)
    {
        if (!$this->owner->ID) {
            return;
        }

        // Avoid adding gridfield twice
        if ($fields->dataFieldByName('RecordLocales')) {
            return;
        }

        // Create gridfield, and get columns from various other extensions
        $config = GridFieldConfig_Base::create();
        /** @var GridFieldDataColumns $columns */
        $columns = $config->getComponentByType(GridFieldDataColumns::class);
        $summaryColumns = [
            'Title'  => 'Title',
            'Locale' => 'Locale'
        ];
        $this->owner->extend('updateLocalisationTabColumns', $summaryColumns);
        $columns->setDisplayFields($summaryColumns);

        // Add actions to each
        $config->addComponents([
            // todo new GroupActionMenu(CopyLocaleAction::COPY_FROM),
            // todo new GroupActionMenu(CopyLocaleAction::COPY_TO),
            new GroupActionMenu(GridField_ActionMenuItem::DEFAULT_GROUP),
            UnpublishAction::create(),
            PublishAction::create(),
        ]);

        /* todo
        // Add each copy from / to
        foreach (Locale::getCached() as $locale) {
            $config->addComponents([
                new CopyLocaleAction($locale->Locale, true),
                new CopyLocaleAction($locale->Locale, false),
            ]);
        }
        */


        // Add gridfield to tab / fields
        $gridField = GridField::create('RecordLocales', 'Locales', $this->LinkedLocales(), $config);
        if ($fields->hasTabSet()) {
            $fields->addFieldToTab('Root.Locales', $gridField);

            $fields
                ->fieldByName('Root.Locales')
                ->setTitle(_t(__CLASS__ . '.TAB_LOCALISATION', 'Localisation'));
        } else {
            $fields->push($gridField);
        }
    }
}
