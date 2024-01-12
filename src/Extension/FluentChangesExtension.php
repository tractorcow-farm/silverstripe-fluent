<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\ChangeSetItem;

/**
 * Adds locale-specific extensions to ChangeSet
 *
 * @extends DataExtension<ChangeSetItem>
 */
class FluentChangesExtension extends DataExtension
{
    /**
     * @see ChangeSetItem::getChangeType()
     *
     * @param string $type
     * @param int    $draftVersion
     * @param int    $liveVersion
     */
    public function updateChangeType(&$type, $draftVersion, $liveVersion)
    {
        if ($type !== ChangeSetItem::CHANGE_NONE) {
            return;
        }

        // Mark any fluent object as modified if otherwise treated as null
        $owner = $this->owner;
        foreach ($owner->Object()->getExtensionInstances() as $extension) {
            if ($extension instanceof FluentExtension) {
                $type = ChangeSetItem::CHANGE_MODIFIED;
                return;
            }
        }
    }
}
