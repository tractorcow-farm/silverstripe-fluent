<?php

namespace TractorCow\Fluent\Forms;

use LogicException;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;
use TractorCow\Fluent\Service\CopyToLocaleService;

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
        $otherLocaleObject = Locale::getByLocale($this->otherLocale);
        if ($otherLocaleObject) {
            return $otherLocaleObject->getLongTitle();
        }
        return null;
    }

    public function getActions($gridField)
    {
        return $this->isTo
            ? ['fluentcopyto']
            : ['fluentcopyfrom'];
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
     * @throws HTTPResponse_Exception
     * @throws ValidationException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        $fromLocale = $arguments['FromLocale'];
        $toLocale = $arguments['ToLocale'];

        if (!$this->validateAction($actionName, $fromLocale, $toLocale)) {
            return;
        }

        if (!$this->validateLocalePermissions($toLocale)) {
            // User doesn't have permissions to use this action
            throw new HTTPResponse_Exception("Action not allowed", 403);
        }

        CopyToLocaleService::singleton()->copyToLocale(
            $arguments['RecordClass'],
            $arguments['RecordID'],
            $fromLocale,
            $toLocale
        );
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
        if ($locale->Locale === $this->otherLocale) {
            return false;
        }

        // Check information of record in source locale
        $fromLocale = $this->isTo
            ? $locale
            : Locale::getByLocale($this->otherLocale);
        if (empty($fromLocale)) {
            throw new LogicException("Error loading locale");
        }

        $fromRecordLocale = RecordLocale::create($record, $fromLocale);
        return $fromRecordLocale->IsDraft();
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param Locale $locale
     * @param string $columnName
     * @return GridField_FormAction
     */
    protected function getButtonAction($gridField, DataObject $record, Locale $locale, $columnName)
    {
        $action = $this->isTo ? 'fluentcopyto' : 'fluentcopyfrom';
        $name = "{$action}_{$locale->Locale}_{$this->otherLocale}_{$record->ID}";
        $toLocale = $this->isTo ? $this->otherLocale : $locale->Locale;

        $title = $this->getTitle($gridField, $record, $columnName);
        $action = GridField_FormAction::create(
            $gridField,
            $name,
            $title,
            $action,
            [
                'RecordID'    => $record->ID,
                'RecordClass' => get_class($record),
                'FromLocale'  => $this->isTo ? $locale->Locale : $this->otherLocale,
                'ToLocale'    => $toLocale,
            ]
        )
            ->addExtraClass(
                'action--fluentpublish btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled'
            )
            ->setAttribute('classNames', 'action--fluentpublish font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        if (!$this->validateLocalePermissions($toLocale)) {
            // User doesn't have permissions to use this action
            $action->setDisabled(true);
        }

        return $action;
    }

    public function getGroup($gridField, $record, $columnName)
    {
        $hasGroup = parent::getGroup($gridField, $record, $columnName);
        if ($hasGroup) {
            return $this->isTo ? CopyLocaleAction::COPY_TO : CopyLocaleAction::COPY_FROM;
        }
        return null;
    }

    /**
     * Ensures that action is executed only once and not once per per locale
     *
     * @param string $actionName
     * @param string $fromLocale
     * @param string $toLocale
     * @return bool
     */
    private function validateAction($actionName, $fromLocale, $toLocale)
    {
        if ($actionName === 'fluentcopyto' && $this->otherLocale === $toLocale) {
            return true;
        }

        if ($actionName === 'fluentcopyfrom' && $this->otherLocale === $fromLocale) {
            return true;
        }

        return false;
    }
}
