<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Delete\DeleteFilterPolicy;
use TractorCow\Fluent\Model\Delete\DeleteLocalisationPolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Provides the "delete this locale" action
 */
class DeleteLocaleAction extends BaseAction
{
    public function getTitle($gridField, $record, $columnName)
    {
        return _t(__CLASS__ . '.DELETE', 'Delete in this locale');
    }

    public function getActions($gridField)
    {
        return ['fluentdelete'];
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
        if ($actionName !== 'fluentdelete') {
            return;
        }

        // Get parent record and locale
        $record = DataObject::get($arguments['RecordClass'])->byID($arguments['RecordID']);
        $locale = Locale::getByLocale($arguments['Locale']);
        if (!$locale || !$record || !$record->isInDB()) {
            return;
        }

        // Unpublish in locale
        FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
            $newState->setLocale($locale->getLocale());

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
    }

    /**
     * Record must be published before it can be unpublished
     *
     * @param DataObject $record
     * @param Locale $locale
     * @return mixed
     */
    protected function appliesToRecord(DataObject $record, Locale $locale)
    {
        return $record->hasExtension(FluentExtension::class)
            || $record->hasExtension(FluentFilteredExtension::class);
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
        $field = GridField_FormAction::create(
            $gridField,
            "FluentDelete_{$locale->Locale}_{$record->ID}",
            $title,
            "fluentdelete",
            [
                'RecordID'    => $record->ID,
                'RecordClass' => get_class($record),
                'Locale'      => $locale->Locale,
            ]
        )
            ->addExtraClass('action--fluentdelete btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--fluentdelete font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        return $field;
    }
}
