<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Delete\DeleteFilterPolicy;
use TractorCow\Fluent\Model\Delete\DeleteLocalisationPolicy;
use TractorCow\Fluent\Model\Delete\DeleteRecordPolicy;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * This class is a {@link GridField} component that adds a delete from locale action for
 * objects.
 */
class DeleteAllLocalesAction implements GridField_ColumnProvider, GridField_ActionProvider, GridField_ActionMenuItem
{
    /**
     * @param GridField  $gridField
     * @param DataObject $record
     * @param            $columnName
     * @return string
     */
    public function getTitle($gridField, $record, $columnName)
    {
        $field = $this->getRemoveAction($gridField, $record, $columnName);

        if ($field) {
            return $field->getAttribute('title');
        }

        return _t(__CLASS__ . '.Delete', 'Delete record in all locales');
    }

    /**
     * @inheritdoc
     */
    public function getGroup($gridField, $record, $columnName)
    {
        $field = $this->getRemoveAction($gridField, $record, $columnName);

        return $field ? GridField_ActionMenuItem::DEFAULT_GROUP : null;
    }

    /**
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return array
     */
    public function getExtraData($gridField, $record, $columnName)
    {

        $field = $this->getRemoveAction($gridField, $record, $columnName);

        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    /**
     * Add a column 'Delete'
     *
     * @param GridField $gridField
     * @param array     $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * Return any special attributes that will be used for FormField::create_tag()
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string    $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return ['title' => ''];
        }

        return null;
    }

    /**
     * Which columns are handled by this component
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return ['deletefluent'];
    }

    /**
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return string|null the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        $field = $this->getRemoveAction($gridField, $record, $columnName);

        if ($field) {
            return $field->Field();
        }

        return null;
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param GridField $gridField
     * @param string    $actionName
     * @param array     $arguments
     * @param array     $data Form data
     * @throws ValidationException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'deletefluent') {

            /** @var DataObject $record */
            $record = $gridField->getList()->byID($arguments['RecordID']);

            if (!$record) {
                return;
            }

            $this->inEveryLocale(function () use ($record) {
                // Delete filtered policy for this locale
                if ($record->hasExtension(FluentFilteredExtension::class)) {
                    $policy = DeleteFilterPolicy::create();
                    $policy->delete($record);
                }

                // Delete all localisations
                if ($record->hasExtension(FluentExtension::class)) {
                    if ($record->hasExtension(Versioned::class)) {
                        $this->inEveryStage(function () use ($record) {
                            $policy = DeleteLocalisationPolicy::create();
                            $policy->delete($record);
                        });
                    } else {
                        $policy = DeleteLocalisationPolicy::create();
                        $policy->delete($record);
                    }
                }
            });

            // Archive base record
            $policy = new DeleteRecordPolicy();
            $policy->delete($record);
        }
    }

    /**
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return GridField_FormAction|null
     */
    private function getRemoveAction($gridField, $record, $columnName)
    {
        if (!$record->canDelete()) {
            return null;
        }
        $title = _t(__CLASS__ . '.Delete', 'Delete record in all locales');

        $field = GridField_FormAction::create(
            $gridField,
            'DeleteFluent' . $record->ID,
            false,
            "deletefluent",
            ['RecordID' => $record->ID]
        )
            ->addExtraClass('action--delete btn--icon-md font-icon-cancel-circled btn--no-text grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--delete font-icon-cancel-circled')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        return $field;
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
