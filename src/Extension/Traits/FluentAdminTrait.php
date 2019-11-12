<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Delete\DeleteLocalisationPolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Decorates admin areas for localised items with extra actions.
 */
trait FluentAdminTrait
{
    public abstract function actionComplete($form, $message);


    /**
     * Decorate actions with fluent-specific details
     *
     * @param FieldList $actions
     * @param DataObject|Versioned $record
     */
    protected function updateFluentActions(FieldList $actions, DataObject $record)
    {
        // If the object exists (is not archived)
        if (!$record->isArchived()) {
            $locale = Locale::getCurrentLocale();

            // Build root tabset that makes up the menu
            $rootTabSet = TabSet::create('FluentMenu')
                ->setTemplate('FluentAdminTabSet');
            $rootTabSet->addExtraClass('ss-ui-action-tabset action-menus fluent-actions-menu noborder');

            // Add menu button
            $moreOptions = Tab::create(
                'FluentMenuOptions',
                'Localisation'
            );
            $moreOptions->addExtraClass('popover-actions-simulate');
            $rootTabSet->push($moreOptions);

            // Add menu items
            $moreOptions->push(
                FormAction::create('clearFluent', "Clear from all except '{$locale->getTitle()}'")
                    ->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create('copyFluent', 'Copy to all other locales')
                    ->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create('unpublishFluent', 'Unpublish (all locales)')
                    ->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create('archiveFluent', 'Unpublish and Archive (all locales)')
                    ->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create('publishFluent', 'Save & Publish (all locales)')
                    ->addExtraClass('btn-secondary')
            );

            $actions->push($rootTabSet);
        }
    }

    /**
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function clearFluent($data, $form)
    {
        // loop over all stages
        // then loop over all locales, invoke DeleteLocalisationPolicy

        $originalLocale = Locale::getCurrentLocale();

        $record = $form->getRecord();

        // Loop over Locales
        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $locale) {

            if ($locale->ID != $originalLocale->ID)
                foreach ([Versioned::LIVE, Versioned::DRAFT] as $stage) {

                    /** @var DataObject|FluentVersionedExtension|RecursivePublishable|Versioned|FluentFilteredExtension $record */
                    $record = Versioned::get_by_stage($record->ClassName, $stage)->byID($record->ID);

                    // Set the current locale
                    FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
                        $newState->setLocale($locale->getLocale());

                        // Unpublish in localisation
                        /** @var DataObject|FluentVersionedExtension|RecursivePublishable|Versioned $fresh */
                        $fresh = $record->get()->byID($record->ID);
                        $fresh->doUnpublish();


                        // after loop, force delete base record with DeleteRecordPolicy
                        $policy = new DeleteLocalisationPolicy();
                        $policy->delete($record);
                    });
                }
        }

        // Restore original state
        FluentState::singleton()->withState(function (FluentState $newState) use ($originalLocale) {
            $newState->setLocale($originalLocale->getLocale());
        });

        // Get the record
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();

        $message = _t(
            __CLASS__ . '.ClearAllNotice',
            "All localisations have been cleared for '{title}'.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);
    }

    /**
     * Copy this record to other localisations (not published)
     *
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function copyFluent($data, $form)
    {
        // Get the record
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();

        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $locale) {

            // Set the current locale
            FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
                $newState->setLocale($locale->getLocale());
                /** @var DataObject|FluentVersionedExtension|RecursivePublishable|Versioned $fresh */
                $fresh = $record->get()->byID($record->ID);

                $fresh->writeToStage(Versioned::DRAFT);

                // Enable if filterable too
                /** @var DataObject|FluentFilteredExtension $fresh */
                if ($fresh->hasExtension(FluentFilteredExtension::class)) {
                    $fresh->FilteredLocales()->add($locale);
                }
            });
        }

        $message = _t(
            __CLASS__ . '.CopyNotice',
            "Copied '{title}' to all other locales.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);
    }

    /**
     * Unpublishes the current object from all locales
     *
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function unpublishFluent($data, $form)
    {
        // in live mode
        // loop over all locales
        // delete locale DeleteLocalisationPolicy
        // delete live record

        $originalStage = Versioned::get_reading_mode();

        // Set Live
        Versioned::set_reading_mode(Versioned::LIVE);

        // Get the record
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();

        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $locale) {
            // Set the current locale
            FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {

                // UnPublish the record
                $newState->setLocale($locale->getLocale());
                /** @var DataObject|FluentVersionedExtension|RecursivePublishable|Versioned $fresh */
                $fresh = $record->get()->byID($record->ID);
                $fresh->doUnpublish();

                // Disable from filter as well
                /** @var DataObject|FluentFilteredExtension $fresh */
                if ($fresh->hasExtension(FluentFilteredExtension::class)) {
                    $fresh->FilteredLocales()->remove($locale);
                }
            });
        }

        $message = _t(
            __CLASS__ . '.UnpublishNotice',
            "Unpublished '{title}' from all locales.",
            ['title' => $record->Title]
        );

        // Restore original reading mode
        Versioned::set_reading_mode($originalStage);

        return $this->actionComplete($form, $message);
    }

    /**
     * Archives the current object from all locales
     *
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function archiveFluent($data, $form)
    {
        // loop over all locales
        // invoke DeleteLocalisationPolicy
        // archive record

        // Get the record
        /** @var DataObject|SiteTree|FluentFilteredExtension $record */
        $record = $form->getRecord();

        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $locale) {
            // Set the current locale
            FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {

                // UnPublish the record
                $newState->setLocale($locale->getLocale());

                // Enable if filterable too
                if ($record->hasExtension(FluentFilteredExtension::class)) {
                    $record->FilteredLocales()->remove($locale);
                }

                $record->doArchive();
            });
        }

        $message = _t(
            __CLASS__ . '.ArchiveNotice',
            "Deleted '{title}' and all of its localisations.",
            ['title' => $record->Title]
        );


        // after loop, force delete base record with DeleteRecordPolicy
        $policy = new DeleteLocalisationPolicy();
        $policy->delete($record);

        return $this->actionComplete($form, $message);

    }

    /**
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function publishFluent($data, $form)
    {
        // loop over all locales
        // publish each

        // Get the record
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();

        /** @var Locale $localeObj */
        foreach (Locale::getCached() as $locale) {
            // Set the current locale
            FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
                $newState->setLocale($locale->getLocale());
                /** @var DataObject|FluentVersionedExtension|RecursivePublishable|Versioned $fresh */
                $fresh = $record->get()->byID($record->ID);

                $fresh->publishRecursive();

                // Enable if filterable too
                /** @var DataObject|FluentFilteredExtension $fresh */
                if ($fresh->hasExtension(FluentFilteredExtension::class)) {
                    $fresh->FilteredLocales()->add($locale);
                }
            });
        }

        $message = _t(
            __CLASS__ . '.PublishNotice',
            "Published '{title}' across all locales.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);
    }
}


