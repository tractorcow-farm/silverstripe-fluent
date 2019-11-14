<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Delete\ArchiveRecordPolicy;
use TractorCow\Fluent\Model\Delete\DeleteFilterPolicy;
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
     * @param FieldList            $actions
     * @param DataObject|Versioned $record
     */
    protected function updateFluentActions(FieldList $actions, DataObject $record)
    {
        // Skip if object isn't localised
        if (!$record->hasExtension(FluentExtension::class)) {
            return;
        }

        // Skip if record isn't saved
        if (!$record->isInDB()) {
            return;
        }

        // Skip if record is archived
        $results = $record->invokeWithExtensions('isArchived');
        $results = array_filter($results, function ($v) {
            return !is_null($v);
        });
        $isArchived = ($results) ? min($results) : false;
        if ($isArchived) {
            return;
        }

        // If there are no results, this will pass as true
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
            FormAction::create('copyFluent', "Copy '{$locale->getTitle()}' to other locales")
                ->addExtraClass('btn-secondary')
        );

        // Versioned specific items
        if ($record->hasExtension(Versioned::class)) {
            $moreOptions->push(
                FormAction::create('unpublishFluent', 'Unpublish (all locales)')
                    ->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create('archiveFluent', 'Unpublish and Archive (all locales)')
                    ->addExtraClass('btn-outline-danger')
            );
            $moreOptions->push(
                FormAction::create('publishFluent', 'Save & Publish (all locales)')
                    ->addExtraClass('btn-primary')
            );
        }

        // Make sure the menu isn't going to get cut off
        $actions->insertBefore('RightGroup', $rootTabSet);
    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     */
    public function clearFluent($data, $form)
    {
        // loop over all stages
        // then loop over all locales, invoke DeleteLocalisationPolicy

        $originalLocale = Locale::getCurrentLocale();

        // Get the record
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();

        // Loop over other Locales
        $this->inEveryLocale(function (Locale $locale) use ($record, $originalLocale) {
            // Skip original locale
            if ($locale->ID == $originalLocale->ID) {
                return;
            }

            $this->inEveryStage(function () use ($record) {
                // after loop, force delete base record with DeleteRecordPolicy
                $policy = DeleteLocalisationPolicy::create();
                $policy->delete($record);
            });
        });

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
     * @param Form  $form
     * @return mixed
     */
    public function copyFluent($data, $form)
    {
        // Write current record to every other stage
        /** @var DataObject|SiteTree $record */
        $record = $form->getRecord();
        $this->inEveryLocale(function () use ($record) {
            if ($record->hasExtension(Versioned::class)) {
                $record->writeToStage(Versioned::DRAFT);
            } else {
                $record->write();
            }
        });

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
     * @param Form  $form
     * @return mixed
     */
    public function unpublishFluent($data, $form)
    {
        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $this->inEveryLocale(function () use ($record) {
            $record->doUnpublish();
        });

        $message = _t(
            __CLASS__ . '.UnpublishNotice',
            "Unpublished '{title}' from all locales.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);
    }

    /**
     * Archives the current object from all locales
     *
     * @param array $data
     * @param Form  $form
     * @return mixed
     */
    public function archiveFluent($data, $form)
    {
        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();

        $this->inEveryLocale(function () use ($record) {
            // Delete filtered policy for this locale
            if ($record->hasExtension(FluentFilteredExtension::class)) {
                $policy = DeleteFilterPolicy::create();
                $policy->delete($record);
            }

            // Delete all localisations
            if ($record->hasExtension(FluentExtension::class)) {
                $this->inEveryStage(function () use ($record) {
                    $policy = DeleteLocalisationPolicy::create();
                    $policy->delete($record);
                });
            }
        });

        // Archive base record
        $policy = ArchiveRecordPolicy::create();
        $policy->delete($record);

        $message = _t(
            __CLASS__ . '.ArchiveNotice',
            "Deleted '{title}' and all of its localisations.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);

    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws ValidationException
     */
    public function publishFluent($data, $form)
    {
        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();

        // save form data into record
        $form->saveInto($record);
        $record->write();

        $this->inEveryLocale(function (Locale $locale) use ($record) {
            // Publish record
            $record->publishRecursive();

            // Enable if filterable too
            /** @var DataObject|FluentFilteredExtension $record */
            if ($record->hasExtension(FluentFilteredExtension::class)) {
                $record->FilteredLocales()->add($locale);
            }
        });

        $message = _t(
            __CLASS__ . '.PublishNotice',
            "Published '{title}' across all locales.",
            ['title' => $record->Title]
        );

        return $this->actionComplete($form, $message);
    }

    /**
     * Do an action in every locale
     *
     * @param callable $doSomething
     */
    protected function inEveryLocale($doSomething)
    {
        foreach (Locale::getCached() as $locale) {
            FluentState::singleton()->withState(function (FluentState $newState) use ($doSomething, $locale) {
                $newState->setLocale($locale->getLocale());
                $doSomething($locale);
            });
        }
    }

    /**
     * Do an action in every stage (Live first)
     *
     * @param callable $doSomething
     */
    protected function inEveryStage($doSomething)
    {
        // For each locale / stage, delete content
        foreach ([Versioned::LIVE, Versioned::DRAFT] as $stage) {
            Versioned::withVersionedMode(function () use ($doSomething, $stage) {
                Versioned::set_stage($stage);
                // Set current locale
                $doSomething($stage);
            });
        }
    }
}


