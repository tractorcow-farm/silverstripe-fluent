<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use TractorCow\Fluent\Model\Locale;

/**
 * Base action for acting on a single locale / record pair
 *
 * Note: Any of these actions can be applied either to a list of locales
 * on a single record, or a list of records in the current locale.
 */
abstract class BaseAction implements GridField_ActionProvider, GridField_ActionMenuItem
{
    use Injectable;

    /**
     * @param GridField  $gridField
     * @param DataObject $record
     * @param Locale     $locale
     * @param string     $columnName
     * @return GridField_FormAction|null
     */
    abstract protected function getButtonAction($gridField, DataObject $record, Locale $locale, $columnName);

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
     * @param DataObject $record Row record
     * @param string     $columnName
     * @return array|null the attributes for the action
     */
    public function getExtraData($gridField, $record, $columnName)
    {
        list($localisedRecord, $locale) = $this->getRecordAndLocale($gridField, $record);
        $field = $this->getButtonAction($gridField, $localisedRecord, $locale, $columnName);
        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    /**
     * @param GridField  $gridField
     * @param DataObject $record Row record
     * @param string     $columnName
     * @return null|string
     */
    public function getGroup($gridField, $record, $columnName)
    {
        list($localisedRecord, $locale) = $this->getRecordAndLocale($gridField, $record);
        if ($locale instanceof Locale && $this->appliesToRecord($localisedRecord, $locale)) {
            return GridField_ActionMenuItem::DEFAULT_GROUP;
        }
        return null;
    }

    /**
     * Given a gridfield, and either an ID or record, return a list with
     * both the record  being localised, and the locale object
     *
     * @param GridField  $gridField Gridfield
     * @param DataObject $rowRecord Record in row
     * @return array 2 length array with localised record, and locale as adjacent items
     */
    protected function getRecordAndLocale(GridField $gridField, DataObject $rowRecord)
    {
        $baseRecord = $gridField->getForm()->getRecord();

        // Gridfield is list of locales for a single localised object
        // E.g. list of locales for a single record
        if ($rowRecord instanceof Locale) {
            return [$baseRecord, $rowRecord];
        }

        // Gridfield is list of localised object in current locale
        // E.g. list of blog posts in one locale
        $locale = Locale::getCurrentLocale();
        return [$rowRecord, $locale];
    }

    /**
     * Validate locale permission for specific locale
     *
     * @param string $locale
     * @return bool
     */
    protected function validateLocalePermissions(string $locale): bool
    {
        if (Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            // The user has permission to access all locales, no further checks are needed
            return true;
        }

        $localeObject = Locale::getByLocale($locale);

        // Validate permission of the target locale
        return Permission::check($localeObject->getLocaleEditPermission());
    }
}
