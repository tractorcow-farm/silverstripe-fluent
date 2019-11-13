<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Model\Locale;

/**
 * Base action for acting on a single locale / record pair
 */
abstract class BaseAction implements GridField_ActionProvider, GridField_ActionMenuItem
{
    use Injectable;

    /**
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return GridField_FormAction|null
     */
    abstract protected function getButtonAction($gridField, $record, $columnName);

    /**
     * Check if this item is enabled for the given record in locale
     *
     * @param DataObject $record
     * @param Locale     $locale
     * @return mixed
     */
    abstract protected function appliesToRecord(DataObject $record, Locale $locale);

    /**
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return array|null the attributes for the action
     */
    public function getExtraData($gridField, $record, $columnName)
    {
        // return ["classNames" => "font-icon-translatable action-detail edit-link"];
        $field = $this->getButtonAction($gridField, $record, $columnName);
        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    /**
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return null|string
     */
    public function getGroup($gridField, $record, $columnName)
    {
        if ($record instanceof Locale
            && $this->appliesToRecord($gridField->getForm()->getRecord(), $record)
        ) {
            return GridField_ActionMenuItem::DEFAULT_GROUP;
        }
        return null;
    }
}
