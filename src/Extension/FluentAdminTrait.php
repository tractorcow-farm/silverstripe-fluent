<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;

/**
 * Decorates admin areas for localised items with extra actions
 *
 * @todo Move out of trait into injectable service
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
        $rootTabSet = new TabSet('FluentMenu');
        $rootTabSet->addExtraClass('ss-ui-action-tabset action-menus fluent-actions-menu noborder');

        // Add menu button
        $moreOptions = new Tab(
            'FluentMenuOptions',
            'Localisation'
        );
        $moreOptions->addExtraClass('popover-actions-simulate');
        $rootTabSet->push($moreOptions);

        // Add menu items
        $moreOptions->push(
            FormAction::create('unpublishFluent', 'Unpublish all')
                ->addExtraClass('btn-secondary')
                ->setDescription('Remove all localisations from live')
        );
        $moreOptions->push(
            FormAction::create('publishFluent', 'Publish all')
                ->addExtraClass('btn-secondary')
                ->setDescription('Publish each locale live')
        );
        $moreOptions->push(
            FormAction::create('clearFluent', 'Clear all')
                ->addExtraClass('btn-secondary')
                ->setDescription('Remove all localisations from live / draft')
        );
        $moreOptions->push(
            FormAction::create('copyFluent', 'Copy all')
                ->addExtraClass('btn-secondary')
                ->setDescription('Copy this locale to all other locales')
        );

        $actions->push($rootTabSet);
    }
}
