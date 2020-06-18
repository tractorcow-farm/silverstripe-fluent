<?php

namespace TractorCow\Fluent\Forms;

use LogicException;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Model\RecordLocale;
use TractorCow\Fluent\State\FluentState;

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
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if (!$this->validateAction($actionName, $arguments['FromLocale'], $arguments['ToLocale'])) {
            return;
        }

        // Check permissions for adding global actions
        if (!Permission::check(Locale::CMS_ACCESS_MULTI_LOCALE)) {
            throw new HTTPResponse_Exception("Action not allowed", 403);
        }

        // Load record in base locale
        FluentState::singleton()->withState(function (FluentState $sourceState) use ($arguments) {
            $fromLocale = Locale::getByLocale($arguments['FromLocale']);
            if (!$fromLocale) {
                return;
            }
            $sourceState->setLocale($fromLocale->getLocale());

            // Load record in source locale
            $record = DataObject::get($arguments['RecordClass'])->byID($arguments['RecordID']);
            if (!$record) {
                return;
            }

            // Save record to other locale
            $sourceState->withState(function (FluentState $destinationState) use ($record, $arguments) {
                $toLocale = Locale::getByLocale($arguments['ToLocale']);
                if (!$toLocale) {
                    return;
                }
                $destinationState->setLocale($toLocale->getLocale());

                // Write
                /** @var DataObject|Versioned $record */
                if ($record->hasExtension(Versioned::class)) {
                    $record->writeToStage(Versioned::DRAFT);
                } else {
                    $record->forceChange();
                    $record->write();
                }
            });
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

        /** @var RecordLocale $fromRecordLocale */
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

        $title = $this->getTitle($gridField, $record, $columnName);
        return GridField_FormAction::create(
            $gridField,
            $name,
            $title,
            $action,
            [
                'RecordID'    => $record->ID,
                'RecordClass' => get_class($record),
                'FromLocale'  => $this->isTo ? $locale->Locale : $this->otherLocale,
                'ToLocale'    => $this->isTo ? $this->otherLocale : $locale->Locale,
            ]
        )
            ->addExtraClass(
                'action--fluentpublish btn--icon-md font-icon-translatable grid-field__icon-action action-menu--handled'
            )
            ->setAttribute('classNames', 'action--fluentpublish font-icon-translatable')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);
    }

    public function getGroup($gridField, $record, $columnName)
    {
        $hasGroup = parent::getGroup($gridField, $record, $columnName);
        if ($hasGroup) {
            return $this->isTo ? self::COPY_TO : self::COPY_FROM;
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
