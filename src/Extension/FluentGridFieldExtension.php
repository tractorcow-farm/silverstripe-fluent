<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBField;
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
     * @see VersionedGridFieldItemRequest::Breadcrumbs()
     * @param DBField|null $badgeField
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
}
