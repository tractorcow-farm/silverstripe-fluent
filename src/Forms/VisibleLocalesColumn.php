<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\Traits\FluentBadgeTrait;
use TractorCow\Fluent\Model\Locale;

/**
 * Adds a "visible in locales" column to a gridfield
 */
class VisibleLocalesColumn implements GridField_ColumnProvider
{
    use FluentBadgeTrait;

    /**
     * Modify the list of columns displayed in the table.
     *
     * @param GridField $gridField
     * @param array     $columns List reference of all column names.
     * @see {@link GridFieldDataColumns->getDisplayFields()}
     * @see {@link GridFieldDataColumns}.
     *
     */
    public function augmentColumns($gridField, &$columns)
    {
        $key = array_search('Actions', $columns);
        if ($key !== false) {
            // Ensure Actions always appears as the last column.
            unset($columns[$key]);
            $columns = array_merge($columns, [
                'Locales',
                'Actions',
            ]);
        } else {
            // Append to end if no Actions column
            $columns[] = 'Locales';
        }
    }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return ['Locales'];
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param GridField                  $gridField
     * @param DataObject|FluentExtension $record - Record displayed in this row
     * @param string                     $columnName
     * @return string - HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if ($columnName !== 'Locales') {
            return null;
        }

        $label = '';
        foreach (Locale::getLocales() as $locale) {
            $label .= $this->generateBadgeHTML($record, $locale);
        }

        return DBField::create_field('HTMLFragment', $label);
    }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param GridField  $gridField
     * @param DataObject $record displayed in this row
     * @param string     $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return [
            'class' => 'fluent-visible-column',
        ];
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string    $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Locales') {
            return [
                'title' => _t(__CLASS__ . '.LocalesTitle', 'Locales', 'Column title for locales')
            ];
        }
        return null;
    }
}
