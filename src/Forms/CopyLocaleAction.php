<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;

class CopyLocaleAction extends BaseAction
{
    /**
     * Other locale to copy between
     *
     * @var string
     */
    protected $otherLocale;

    /**
     * If true, this is "copy to $otherlocale". If false "copy from $otherLocale"
     *
     * @var bool
     */
    protected $isTo;

    const COPY_TO = 'COPY_TO';

    const COPY_FROM = 'COPY_FROM';

    /**
     * CopyLocaleAction constructor.
     *
     * @param string $otherLocale Other locale to interact with
     * @param bool $isTo Is this copying to the given locale? Otherwise, assume copy from
     */
    public function __construct($otherLocale, $isTo)
    {
        $this->otherLocale = $otherLocale;
        $this->isTo = $isTo;
    }

    public function getTitle($gridField, $record, $columnName)
    {
        return $this->isTo
            ? _t(__CLASS__ . '.TO', 'Copy to {locale}', ['locale' => $this->otherLocale])
            : _t(__CLASS__ . '.FROM', 'Copy from {locale}', ['locale' => $this->otherLocale]);
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
     * @param string    $actionName Action identifier, see {@link getActions()}.
     * @param array     $arguments  Arguments relevant for this
     * @param array     $data       All form data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        // @todo
    }

    /**
     * Item needs to be translated before it can be published
     *
     * @param DataObject $record
     * @param Locale     $locale
     * @return mixed
     */
    protected function appliesToRecord(DataObject $record, Locale $locale)
    {
        if ($locale->Locale === $this->otherLocale) {
            return false;
        }

        $fromLocale = $this->isTo ? $locale->Locale : $this->otherLocale;

        /** @var DataObject|FluentVersionedExtension $record */
        return $record
            && $record->hasExtension(FluentExtension::class)
            && $record->isDraftedInLocale($fromLocale);
    }


    /**
     *
     * @param GridField  $gridField
     * @param DataObject $record
     * @param string     $columnName
     * @return GridField_FormAction|null
     */
    protected function getButtonAction($gridField, $record, $columnName)
    {
        $name = ($this->isTo ? 'fluentcopyto' : 'fluentcopyfrom') . $record->ID;
        $title = $this->getTitle($gridField, $record, $columnName);
        $field = GridField_FormAction::create(
            $gridField,
            $name,
            $title,
            $name,
            ['RecordID' => $record->ID]
        )
            ->addExtraClass('action--fluentpublish btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--fluentpublish font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        return $field;
    }

    public function getGroup($gridField, $record, $columnName)
    {
        if ($record instanceof Locale
            && $this->appliesToRecord($gridField->getForm()->getRecord(), $record)
        ) {
            return $this->isTo ? self::COPY_TO : self::COPY_FROM;
        }
    }
}
