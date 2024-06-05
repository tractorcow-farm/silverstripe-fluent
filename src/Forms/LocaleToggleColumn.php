<?php

namespace TractorCow\Fluent\Forms;

use LogicException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_SaveHandler;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;

class LocaleToggleColumn implements GridField_SaveHandler, GridField_ColumnProvider
{
    use Injectable;

    const COLUMN_NAME = 'VisibleToggle';

    /**
     * @inheritDoc
     */
    public function augmentColumns($gridField, &$columns)
    {
        // Add "enabled in" column
        if (in_array(LocaleToggleColumn::COLUMN_NAME, $columns)) {
            return;
        }

        // Add after 'Locale' column
        $localeIndex = array_search('Locale', $columns);
        if ($localeIndex !== false) {
            array_splice(
                $columns,
                $localeIndex + 1,
                0,
                [LocaleToggleColumn::COLUMN_NAME]
            );
        } else {
            $columns[] = LocaleToggleColumn::COLUMN_NAME;
        }
    }

    /**
     * @inheritDoc
     */
    public function getColumnsHandled($gridField)
    {
        return [LocaleToggleColumn::COLUMN_NAME];
    }

    /**
     * @param GridField $gridField
     * @param Locale $locale
     * @param string $columnName
     * @return string
     */
    public function getColumnContent($gridField, $locale, $columnName)
    {
        if ($columnName !== LocaleToggleColumn::COLUMN_NAME) {
            return null;
        }

        // Get details about this record and locale pair
        $record = $this->getRecord($gridField);
        $recordLocale = RecordLocale::create($record, $locale);

        // Create checkbox for this locale
        return CheckboxField::create(
            $this->getFieldName($gridField, $locale),
            false
        )
            ->setValue($recordLocale->IsVisible())
            ->forTemplate();
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return [
            'class' => 'col-' . preg_replace('/[^\w]/', '-', $columnName)
        ];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        return [
            'title' => _t(__CLASS__ . '.DISPLAY_IN', 'Visible?'),
        ];
    }

    public function handleSave(GridField $gridField, DataObjectInterface $record)
    {
        $value = $gridField->Value();

        // Keys for this value will be list of locales to enable
        $enabledLocales = isset($value[LocaleToggleColumn::COLUMN_NAME])
            ? array_keys($value[LocaleToggleColumn::COLUMN_NAME])
            : [];

        /** @var DataObject|FluentFilteredExtension $record */
        $record = $this->getRecord($gridField);

        // Assign filtered items
        $record->FilteredLocales()->setByIDList($enabledLocales);
    }

    protected function getFieldName(GridField $grid, Locale $locale)
    {
        return sprintf(
            '%s[%s][%s]',
            $grid->getName(),
            LocaleToggleColumn::COLUMN_NAME,
            $locale->ID
        );
    }

    /**
     * Get record to edit
     *
     * @param GridField $gridField
     * @return DataObject|FluentFilteredExtension
     * @throws LogicException
     */
    public function getRecord($gridField)
    {
        $record = $gridField->getForm()->getRecord();
        if (!$record->hasExtension(FluentFilteredExtension::class)) {
            throw new LogicException(
                "This component is only applicable to objects with FluentFilteredExtension applied"
            );
        }
        return $record;
    }
}
