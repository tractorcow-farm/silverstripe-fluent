<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Delete\ArchiveRecordPolicy;
use TractorCow\Fluent\Model\Delete\DeleteFilterPolicy;
use TractorCow\Fluent\Model\Delete\DeleteLocalisationPolicy;
use TractorCow\Fluent\Model\Delete\DeleteRecordPolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Decorates admin areas for localised items with extra actions.
 */
trait FluentAdminTrait
{
    /**
     * @param Form   $form
     * @param string $message
     * @return HTTPResponse|string|DBHTMLText
     */
    abstract public function actionComplete($form, $message);

    /**
     * Decorate actions with fluent-specific details
     *
     * @param FieldList            $actions
     * @param DataObject|Versioned $record
     */
    protected function updateFluentActions(
        FieldList $actions,
        DataObject $record
    ) {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            return;
        }

        // Skip if object isn't localised
        if (!$record->hasExtension(FluentExtension::class)) {
            return;
        }

        // Skip if record isn't saved
        if (!$record->isInDB()) {
            return;
        }

        // Flush data before checking actions
        $record->flushCache(true);

        // These actions need to be executed before we bail out on archived check
        // as the UI needs to be updated even if such case
        $this->updateSaveAction($actions, $record);
        $this->updateDeleteAction($actions, $record);
        $this->updateInformationPanel($actions, $record);

        // Skip if record is archived
        $results = $record->invokeWithExtensions('isArchived');
        $results = array_filter($results, function ($v) {
            return !is_null($v);
        });
        $isArchived = $results ? min($results) : false;

        if ($isArchived) {
            return;
        }

        $locale = Locale::getCurrentLocale();

        if (!$locale) {
            // Potentially no Locales have been created in the system yet.
            return;
        }

        if (!$record->config()->get('batch_actions_enabled')) {
            return;
        }

        // Build root tabset that makes up the menu
        $rootTabSet = TabSet::create('FluentMenu')->setTemplate(
            'FluentAdminTabSet'
        );

        $rootTabSet->addExtraClass(
            'ss-ui-action-tabset action-menus fluent-actions-menu noborder'
        );

        // Add menu button
        $moreOptions = Tab::create(
            'FluentMenuOptions',
            _t(__TRAIT__ . '.Localisation', 'Localisation')
        );
        $moreOptions->addExtraClass('popover-actions-simulate');
        $rootTabSet->push($moreOptions);

        // Add menu items
        $moreOptions->push(
            FormAction::create(
                'clearFluent',
                _t(
                    __TRAIT__ . '.Label_clearFluent',
                    "Clear from all except '{title}'",
                    [
                        'title' => $locale->getTitle()
                    ]
                )
            )->addExtraClass('btn-secondary')
        );
        $moreOptions->push(
            FormAction::create(
                'copyFluent',
                _t(
                    __TRAIT__ . '.Label_copyFluent',
                    "Copy '{title}' to other locales",
                    [
                        'title' => $locale->getTitle()
                    ]
                )
            )->addExtraClass('btn-secondary')
        );

        // Versioned specific items
        if ($record->hasExtension(Versioned::class)) {
            $moreOptions->push(
                FormAction::create(
                    'unpublishFluent',
                    _t(
                        __TRAIT__ . '.Label_unpublishFluent',
                        'Unpublish (all locales)'
                    )
                )->addExtraClass('btn-secondary')
            );
            $moreOptions->push(
                FormAction::create(
                    'archiveFluent',
                    _t(
                        __TRAIT__ . '.Label_archiveFluent',
                        'Unpublish and Archive (all locales)'
                    )
                )->addExtraClass('btn-outline-danger')
            );
            $moreOptions->push(
                FormAction::create(
                    'publishFluent',
                    _t(
                        __TRAIT__ . '.Label_publishFluent',
                        'Save & Publish (all locales)'
                    )
                )->addExtraClass('btn-primary')
            );
        } else {
            $moreOptions->push(
                FormAction::create(
                    'deleteFluent',
                    _t(
                        __TRAIT__ . '.Label_deleteFluent',
                        'Delete (all locales)'
                    )
                )->addExtraClass('btn-outline-danger')
            );
        }

        // Filtered specific actions
        /** @var DataObject|FluentFilteredExtension $record */
        if ($record->hasExtension(FluentFilteredExtension::class)) {
            if ($record->isAvailableInLocale($locale)) {
                $moreOptions->push(
                    FormAction::create(
                        'hideFluent',
                        _t(
                            __TRAIT__ . '.Label_hideFluent',
                            "Hide from '{title}'",
                            [
                                'title' => $locale->getTitle()
                            ]
                        )
                    )->addExtraClass('btn-outline-danger')
                );
            } else {
                $moreOptions->push(
                    FormAction::create(
                        'showFluent',
                        _t(
                            __TRAIT__ . '.Label_showFluent',
                            "Show in '{title}'",
                            [
                                'title' => $locale->getTitle()
                            ]
                        )
                    )->addExtraClass('btn-outline-primary')
                );
            }
        }

        // Make sure the menu isn't going to get cut off
        $actions->insertBefore('RightGroup', $rootTabSet);
    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function clearFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // loop over all stages
        // then loop over all locales, invoke DeleteLocalisationPolicy

        $originalLocale = Locale::getCurrentLocale();

        // Get the record
        /** @var DataObject $record */
        $record = $form->getRecord();
        $record->flushCache(true);

        // Loop over other Locales
        $this->inEveryLocale(function (Locale $locale) use (
            $record,
            $originalLocale
        ) {
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
            __TRAIT__ . '.ClearAllNotice',
            "All localisations have been cleared for '{title}'.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * Copy this record to other localisations (not published)
     *
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function copyFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Write current record to every other stage
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $record->flushCache(true);

        $originalLocale = Locale::getCurrentLocale();

        $this->inEveryLocale(function (Locale $locale) use ($record, $originalLocale) {
            // Skip original locale
            if ($locale->ID == $originalLocale->ID) {
                return;
            }
            
            if ($record->hasExtension(Versioned::class)) {
                $record->writeToStage(Versioned::DRAFT);
            } else {
                $record->forceChange();
                $record->write();
            }
        });

        $message = _t(
            __TRAIT__ . '.CopyNotice',
            "Copied '{title}' to all other locales.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * Unpublishes the current object from all locales
     *
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function unpublishFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $record->flushCache(true);

        $this->inEveryLocale(function () use ($record) {
            $record->doUnpublish();
        });

        $message = _t(
            __TRAIT__ . '.UnpublishNotice',
            "Unpublished '{title}' from all locales.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * Archives the current object from all locales (versioned)
     *
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function archiveFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $record->flushCache(true);

        $this->inEveryLocale(function () use ($record) {
            // Delete filtered policy for this locale
            if ($record->hasExtension(FluentFilteredExtension::class)) {
                $policy = DeleteFilterPolicy::create();
                $policy->delete($record);
            }

            // Delete all localisations in all locales
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
            __TRAIT__ . '.ArchiveNotice',
            "Archived '{title}' and all of its localisations.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * Delete the current object from all locales (non-versioned)
     *
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function deleteFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $record->flushCache(true);

        $this->inEveryLocale(function () use ($record) {
            // Delete filtered policy for this locale
            if ($record->hasExtension(FluentFilteredExtension::class)) {
                $policy = DeleteFilterPolicy::create();
                $policy->delete($record);
            }

            // Delete all localisations
            if ($record->hasExtension(FluentExtension::class)) {
                $policy = DeleteLocalisationPolicy::create();
                $policy->delete($record);
            }
        });

        // Delete base record
        $policy = DeleteRecordPolicy::create();
        $policy->delete($record);

        $message = _t(
            __TRAIT__ . '.DeleteNotice',
            "Deleted '{title}' and all of its localisations.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws ValidationException
     * @throws HTTPResponse_Exception
     */
    public function publishFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Get the record
        /** @var DataObject|Versioned $record */
        $record = $form->getRecord();
        $record->flushCache(true);

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
            __TRAIT__ . '.PublishNotice',
            "Published '{title}' across all locales.",
            ['title' => $record->Title]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function showFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Show record
        /** @var DataObject|FluentFilteredExtension $record */
        $record = $form->getRecord();
        $record->flushCache(true);
        $locale = Locale::getCurrentLocale();
        $record->FilteredLocales()->add($locale);

        $message = _t(
            __TRAIT__ . '.ShowNotice',
            "Record '{title}' is now visible in {locale}",
            [
                'title'  => $record->Title,
                'locale' => $locale->Title
            ]
        );

        $record->flushCache(true);
        return $this->actionComplete($form, $message);
    }

    /**
     * @param array $data
     * @param Form  $form
     * @return mixed
     * @throws HTTPResponse_Exception
     */
    public function hideFluent($data, $form)
    {
        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        // Show record
        /** @var DataObject|FluentFilteredExtension $record */
        $record = $form->getRecord();
        $record->flushCache(true);
        $locale = Locale::getCurrentLocale();
        $record->FilteredLocales()->remove($locale);

        $message = _t(
            __TRAIT__ . '.HideNotice',
            "Record '{title}' is now hidden in {locale}",
            [
                'title'  => $record->Title,
                'locale' => $locale->Title
            ]
        );

        $record->flushCache(true);
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
            FluentState::singleton()->withState(function (
                FluentState $newState
            ) use (
                $doSomething,
                $locale
            ) {
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
            Versioned::withVersionedMode(function () use (
                $doSomething,
                $stage
            ) {
                Versioned::set_stage($stage);
                // Set current locale
                $doSomething($stage);
            });
        }
    }

    /**
     * Update save action based on localisation state
     * valid states:
     * - localised (localise label)
     * - not localised (save label)
     *
     * @param FieldList $actions
     * @param DataObject|FluentExtension $record
     */
    protected function updateSaveAction(FieldList $actions, DataObject $record): void
    {
        if ($record->existsInLocale()) {
            // keep the action unchanged as the record is localised
            return;
        }

        if (!$record->config()->get('localise_actions_enabled')) {
            // Save action UI is disabled
            $actions->removeByName('action_doSave');

            return;
        }

        $saveAction = $actions->fieldByName('MajorActions.action_doSave');

        if (!$saveAction) {
            return;
        }

        // update save action label to localise in case record is not localised yet
        $saveAction
            ->setTitle(_t('TractorCow\\Fluent\\Extension\\FluentExtension.Localise', 'Localise'))
            ->removeExtraClass('font-icon-save')
            ->addExtraClass('font-icon-translatable');
    }

    /**
     * Update delete action based on localisation state
     * valid states:
     * - localised more than one locale (unlocalise label)
     * - localised in one locale (delete label)
     * - not localised (remove the action)
     *
     * @param FieldList $actions
     * @param DataObject|FluentExtension $record
     */
    protected function updateDeleteAction(FieldList $actions, DataObject $record): void
    {
        if (!$record->existsInLocale()) {
            // record is not localised  - remove the action
            $actions->removeByName('action_doDelete');

            return;
        }

        $locales = $record->getLocaleInstances();

        if (count($locales) <= 1) {
            // keep the action unchanged as this is the last locale
            return;
        }

        $deleteAction = $actions->fieldByName('action_doDelete');

        if (!$deleteAction) {
            return;
        }

        // update delete action label to unlocalise in case there are still more localised instances of the record left
        $deleteAction
            ->setTitle(_t('TractorCow\\Fluent\\Extension\\FluentExtension.Unlocalise', 'Unlocalise'))
            ->removeExtraClass('font-icon-trash-bin')
            ->addExtraClass('font-icon-translatable')
            ->setAttribute(
                'title',
                _t(
                    'TractorCow\\Fluent\\Extension\\FluentExtension.UnlocaliseTooltip',
                    'Remove {name} from current locale',
                    [
                        'name' => $record->i18n_singular_name()
                    ]
                )
            );
    }

    /**
     * Information panel shows published state of a base record by default
     * this overrides the display with the published state of the localised record
     *
     * @param FieldList $actions
     * @param DataObject|FluentVersionedExtension $record
     */
    protected function updateInformationPanel(FieldList $actions, DataObject $record): void
    {
        if (!$record->hasExtension(Versioned::class)) {
            // This is only relevant for versioned records (base)
            return;
        }

        if (!$record->hasExtension(FluentVersionedExtension::class)) {
            // This is only relevant for versioned records (fluent)
            return;
        }

        /** @var Tab $moreOptions */
        $moreOptions = $actions->fieldByName('ActionMenus.MoreOptions');

        if (!$moreOptions) {
            return;
        }

        /** @var LiteralField $information */
        $information = $moreOptions->fieldByName('Information');

        if (!$information) {
            return;
        }

        $liveRecord = Versioned::withVersionedMode(function () use ($record) {
            Versioned::set_stage(Versioned::LIVE);

            return DataObject::get_by_id($record->ClassName, $record->ID);
        });

        $infoTemplate = SSViewer::get_templates_by_class(
            $record->ClassName,
            '_Information',
            $record instanceof SiteTree ? SiteTree::class : DataObject::class
        );

        // show published info of localised record, not base record (this is framework's default)
        $information->setValue($record->customise([
            'Live' => $liveRecord,
            'ExistsOnLive' => $record->isPublishedInLocale(),
        ])->renderWith($infoTemplate));
    }
}
