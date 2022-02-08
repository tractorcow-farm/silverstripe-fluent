<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Provides the "publish this locale" action
 */
class PublishAction extends BaseAction
{
    public function getTitle($gridField, $record, $columnName)
    {
        return _t(__CLASS__ . '.PUBLISH', 'Publish in this locale');
    }

    public function getActions($gridField)
    {
        return ['fluentpublish'];
    }

    /**
     * Handle an action on the given {@link GridField}.
     *
     * Calls ALL components for every action handled, so the component needs
     * to ensure it only accepts actions it is actually supposed to handle.
     *
     * @param GridField $gridField
     * @param string $actionName Action identifier, see {@link getActions()}.
     * @param array $arguments Arguments relevant for this
     * @param array $data All form data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName !== 'fluentpublish') {
            return;
        }

        // Get parent record and locale
        $record = DataObject::get($arguments['RecordClass'])->byID($arguments['RecordID']);
        $locale = Locale::getByLocale($arguments['Locale']);
        if (!$locale || !$record || !$record->isInDB()) {
            return;
        }

        if (!$this->validatePermissions($locale->Locale, $record)) {
            // User doesn't have permissions to use this action
            throw new HTTPResponse_Exception("Action not allowed", 403);
        }

        // Load a fresh record in a new locale, and publish it
        FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
            $newState->setLocale($locale->getLocale());
            /** @var DataObject|FluentVersionedExtension|RecursivePublishable $fresh */
            $fresh = $record->get()->byID($record->ID);
            $fresh->publishRecursive();

            // Enable if filterable too
            /** @var DataObject|FluentFilteredExtension $fresh */
            if ($fresh->hasExtension(FluentFilteredExtension::class)) {
                $fresh->FilteredLocales()->add($locale);
            }
        });
    }

    /**
     * Item needs to be translated before it can be published
     *
     * @param DataObject $record
     * @param Locale $locale
     * @return mixed
     */
    protected function appliesToRecord(DataObject $record, Locale $locale)
    {
        /** @var DataObject|FluentVersionedExtension $record */
        return $record
            && $record->hasExtension(FluentVersionedExtension::class)
            && $record->isDraftedInLocale($locale->Locale);
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param Locale $locale
     * @param string $columnName
     * @return GridField_FormAction|null
     */
    protected function getButtonAction($gridField, DataObject $record, Locale $locale, $columnName)
    {
        $title = $this->getTitle($gridField, $record, $columnName);
        $action = GridField_FormAction::create(
            $gridField,
            "FluentPublish_{$locale->Locale}_{$record->ID}",
            $title,
            "fluentpublish",
            [
                'RecordID'    => $record->ID,
                'RecordClass' => get_class($record),
                'Locale'      => $locale->Locale,
            ]
        )
            ->addExtraClass('action--fluentpublish btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--fluentpublish font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        if (!$this->validatePermissions($locale->Locale, $record)) {
            // User doesn't have permissions to use this action
            $action->setDisabled(true);
        }

        return $action;
    }

    /**
     * Additional permission check - publish
     *
     * @param string $locale
     * @param DataObject $record
     * @return bool
     */
    protected function validatePermissions(string $locale, DataObject $record): bool
    {
        if (!$this->validateLocalePermissions($locale)) {
            return false;
        }

        return FluentState::singleton()->withState(function (FluentState $state) use ($record, $locale): bool {
            $state->setLocale($locale);

            return (bool) $record->canPublish();
        });
    }
}
