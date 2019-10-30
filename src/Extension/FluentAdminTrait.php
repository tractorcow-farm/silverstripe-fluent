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
            FormAction::create('clearFluent', 'Clear locales')
                ->addExtraClass('btn-secondary ')
                ->setDescription('Clear draft version of each locale')
                ->setTemplate('FluentAdminAction')
        );
        $moreOptions->push(
            FormAction::create('copyFluent', 'Copy to locales')
                ->addExtraClass('btn-secondary ')
                ->setDescription('Copy this localisation as draft to each other locale')
                ->setTemplate('FluentAdminAction')
        );
        $moreOptions->push(
            FormAction::create('unpublishFluent', 'Unpublish locales')
                ->addExtraClass('btn-secondary ')
                ->setDescription('Unpublish this page in each locale')
                ->setTemplate('FluentAdminAction')
        );
        $moreOptions->push(
            FormAction::create('publishFluent', 'Publish locales')
                ->addExtraClass('btn-secondary ')
                ->setDescription('Publish this page in each locale')
                ->setTemplate('FluentAdminAction')
        );

        $actions->push($rootTabSet);
    }
}
