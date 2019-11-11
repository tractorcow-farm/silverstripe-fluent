<?php

namespace TractorCow\Fluent\Extension\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;

/**
 * Decorates admin areas for localised items with extra actions.
 */
trait FluentAdminTrait
{
    public abstract function actionComplete($form, $message);


    /**
     * Decorate actions with fluent-specific details
     *
     * @param FieldList  $actions
     * @param DataObject $record
     */
    protected function updateFluentActions(FieldList $actions, DataObject $record)
    {
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
            FormAction::create('clearFluent', 'Clear all localisations')
                ->addExtraClass('btn-secondary')
        );
        // todo
//        $moreOptions->push(
//            FormAction::create('copyFluent', 'Copy this to other locales')
//                ->addExtraClass('btn-secondary')
//        );
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

    public function clearFluent()
    {
        // todo - clear all locales

        // loop over all stages
        // then loop over all locales, invoke DeleteLocalisationPolicy
    }

    public function copyFluent()
    {
        // todo - not important for this release
    }

    public function unpublishFluent($data, $form)
    {
        // in live mode
        // loop over all locales
        // delete locale DeleteLocalisationPolicy
        // delete live record

        return $this->actionComplete($form, 'Records have been unpublished in all locales');
    }

    public function archiveFluent()
    {
        // loop over all locales
        // invoke DeleteLocalisationPolicy
        // archive record

        // after loop, force delete base record with DeleteRecordPolicy
    }

    public function publishFluent()
    {
        // loop over all locales
        // publish each
    }
}


