<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Provides the "Save in this locale" action
 */
class SaveLocaleAction extends BaseAction
{
    public function getTitle($gridField, $record, $columnName)
    {
        return _t(__CLASS__ . '.SAVE', 'Save in this locale');
    }

    public function getActions($gridField)
    {
        return ['fluentsave'];
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
        if ($actionName !== 'fluentsave') {
            return;
        }

        // Get parent record and locale
        $record = DataObject::get($arguments['RecordClass'])->byID($arguments['RecordID']);
        $locale = Locale::getByLocale($arguments['Locale']);
        if (!$locale || !$record || !$record->isInDB()) {
            return;
        }

        // Load a fresh record in a new locale, and publish it
        FluentState::singleton()->withState(function (FluentState $newState) use ($record, $locale) {
            $newState->setLocale($locale->getLocale());
            /** @var DataObject $fresh */
            $fresh = $record->get()->byID($record->ID);
            $fresh->forceChange();
            $fresh->write();

            // Enable if filterable too
            /** @var DataObject|FluentFilteredExtension $fresh */
            if ($fresh->hasExtension(FluentFilteredExtension::class)) {
                $fresh->FilteredLocales()->add($locale);
            }
        });
    }

    /**
     * Item must either be localised, or filtered
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
        return GridField_FormAction::create(
            $gridField,
            "FluentSave_{$locale->Locale}_{$record->ID}",
            $title,
            "fluentsave",
            [
                'RecordID'    => $record->ID,
                'RecordClass' => get_class($record),
                'Locale'      => $locale->Locale,
            ]
        )
            ->addExtraClass('action--fluentsave btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--fluentsave font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);
    }
}
