<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
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

        // Create gridfield
        $config = GridFieldConfig_Base::create();
        /** @var GridFieldDataColumns $columns */
        $columns = $config->getComponentByType(GridFieldDataColumns::class);
        $summaryColumns = [
            'Title'  => 'Title',
            'Locale' => 'Locale'
        ];
        if ($this->owner->hasExtension(FluentFilteredExtension::class)) {
            $summaryColumns['IsVisible'] = [
                'title' => 'Visible',
                'callback' => function (Locale $object) {
                    return $object->RecordLocale()->IsVisible() ? 'visible' : 'hidden';
                }
            ];
        }
        if ($this->owner->hasExtension(FluentVersionedExtension::class)) {
            $summaryColumns['IsDraft'] = [
                'title' => 'Saved',
                'callback' => function (Locale $object) {
                    return $object->RecordLocale()->IsDraft() ? 'Draft' : '';
                }
            ];
            $summaryColumns['IsPublished'] = [
                'title' => 'Published',
                'callback' => function (Locale $object) {
                    return $object->RecordLocale()->IsDraft() ? 'Live' : '';
                }
            ];
        } else {
            $summaryColumns['IsDraft'] = [
                'title' => 'Saved',
                'callback' => function (Locale $object) {
                    return $object->RecordLocale()->IsDraft() ? 'Saved' : '';
                }
            ];
        }
        $columns->setDisplayFields($summaryColumns);


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
