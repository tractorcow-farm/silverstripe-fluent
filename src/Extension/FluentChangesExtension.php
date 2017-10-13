<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\ChangeSetItem;

/**
 * Adds locale-specific extensions to ChangeSet
 */
class FluentChangesExtension extends DataExtension
{
    public function updateChangeType(&$type, $draftVersion, $liveVersion)
    {
        if ($type !== ChangeSetItem::CHANGE_NONE) {
            return;
        }

        // Mark any fluent object as modified if otherwise treated as null
        /** @var ChangeSetItem $owner */
        $owner = $this->owner;
        foreach ($owner->Object()->getExtensionInstances() as $extension) {
            if ($extension instanceof FluentExtension) {
                $type = ChangeSetItem::CHANGE_MODIFIED;
                return;
            }
        }
    }
}
