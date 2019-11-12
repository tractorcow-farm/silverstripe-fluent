<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;
use TractorCow\Fluent\Extension\Traits\FluentBadgeTrait;

/**
 * Supports GridFieldDetailForm_ItemRequest with extra actions
 *
 * @property GridFieldDetailForm_ItemRequest $owner
 */
class FluentGridFieldExtension extends Extension
{
    use FluentAdminTrait;
    use FluentBadgeTrait;

    /**
     * Push a badge to indicate the language that owns the current item
     *
     * @param DBField|null $badgeField
     * @see VersionedGridFieldItemRequest::Breadcrumbs()
     */
    public function updateBadge(&$badgeField)
    {
        $record = $this->owner->getRecord();
        $badgeField = $this->addFluentBadge($badgeField, $record);
    }

    public function updateFormActions(FieldList $actions)
    {
        $this->updateFluentActions($actions, $this->owner->getRecord());
    }

    /**
     * @param Form $form
     * @param string $message
     * @return mixed
     */
    public function actionComplete($form, $message)
    {
        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        $link = $form->getController()->Link();

        // TODO: This redirect doesn't work
        return $this->owner->getController()->redirect($link);
    }
}
