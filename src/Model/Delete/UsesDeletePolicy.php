<?php

namespace TractorCow\Fluent\Model\Delete;

use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\Model\Locale;

/**
 * @property $owner DataObject
 */
trait UsesDeletePolicy
{
    /**
     * Override delete behaviour.
     * Hooks into {@see DataObject::delete()}
     *
     * @param array $queriedTables
     */
    public function updateDeleteTables(&$queriedTables)
    {
        // Ensure a locale exists
        $locale = Locale::getCurrentLocale();
        if (!$locale) {
            return;
        }

        // Solve extension race condition; first extension resets queried tables and defers to delete policy
        if (empty($queriedTables)) {
            return;
        }
        $queriedTables = [];

        $policy = Injector::inst()->create(DeletePolicy::class, $this->owner);
        $policy->delete($this->owner);
    }
}
