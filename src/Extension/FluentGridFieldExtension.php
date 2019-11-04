<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use TractorCow\Fluent\Extension\Traits\FluentAdminTrait;

/**
 * Supports GridFieldDetailForm_ItemRequest with extra actions
 *
 * @property GridFieldDetailForm_ItemRequest $owner
 */
class FluentGridFieldExtension extends Extension
{
    use FluentAdminTrait;

    public function updateFormActions(FieldList $actions)
    {
        $this->updateFluentActions($actions, $this->owner->getRecord());
    }
}
