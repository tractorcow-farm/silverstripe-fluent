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
        $moreOptions->push(
            FormAction::create('copyFluent', 'Copy this to other locales')
                ->addExtraClass('btn-secondary')
        );
        $moreOptions->push(
            FormAction::create('unpublishFluent', 'Unpublish (all locales)')
                ->addExtraClass('btn-secondary')
        );
        $moreOptions->push(
            FormAction::create('unpublishArchive', 'Unpublish and Archive (all locales)')
                ->addExtraClass('btn-secondary')
        );
        $moreOptions->push(
            FormAction::create('publishFluent', 'Save & Publish (all locales)')
                ->addExtraClass('btn-secondary')
        );

        $actions->push($rootTabSet);
    }
}
