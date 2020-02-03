<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member_GroupSet;
use TractorCow\Fluent\State\FluentState;

class FluentMemberExtension extends DataExtension
{
    /**
     * Update groups
     *
     * @param Member_GroupSet $groups
     */
    public function updateGroups(Member_GroupSet &$groups)
    {
        // Filter groups by those that either have no locales selected (same as selected for all),
        // or groups that have the current locale selected.
        $locale = FluentState::singleton()->getLocale();

        $filtered = $groups->filterByCallback(function (Group $group) use ($locale) {
            /** @var Group|FluentGroupExtension $group */
            $enabledLocales = $group->EnabledLocales();

            // Enabled if no locales selected
            if ($enabledLocales->count() === 0) {
                return true;
            }

            // Enabled if the current locale is selected
            if ($enabledLocales->find('Locale', $locale)) {
                return true;
            }

            // Group disabled
            return false;
        });

        // Adjust group filter
        $ids = $filtered->column('ID');
        $groups = $groups->filter('ID', $ids ?: -1);
    }
}
