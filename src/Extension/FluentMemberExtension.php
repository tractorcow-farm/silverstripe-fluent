<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member_GroupSet;
use SilverStripe\Security\Permission;
use TractorCow\Fluent\Model\Locale;
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
        $locale = Locale::getCurrentLocale();
        $filtered = $groups->filterByCallback(function (Group $group) use ($locale) {
            $localePermissions = $this->getLocalePermissionsForGroup($group);

            // Enabled if no locales selected
            if (empty($localePermissions)) {
                return true;
            }

            // Enabled if the current locale is selected, or disabled
            return in_array($locale->getLocaleEditPermission(), $localePermissions);
        });

        // Adjust group filter
        $ids = $filtered->column('ID');
        $groups = $groups->filter('ID', $ids ?: -1);
    }

    /**
     * Get list of locales that the user has CMS access in
     *
     * @return Locale[]|ArrayList
     */
    public function getCMSAccessLocales()
    {
        try {
            return Locale::getCached()->filterByCallback(function (Locale $locale) {
                // Check if the user has CMS access in this locale
                FluentState::singleton()->withState(function (FluentState $state) use ($locale) {
                    $state->setLocale($state);
                    Permission::reset();
                    return Permission::checkMember($this->owner, 'CMS_ACCESS');
                });
            });
        } finally {
            // Ensure permissions aren't affected by any of the above
            Permission::reset();
        }
    }

    /**
     * Get list of locale permission codes
     *
     * @param Group $group
     * @return string[]
     */
    protected function getLocalePermissionsForGroup(Group $group)
    {
        $localePermissions = [];
        /** @var Permission $permission */
        foreach ($group->Permissions() as $permission) {
            $prefix = Locale::CMS_ACCESS_FLUENT_LOCALE;
            $begin = substr($permission->Code, 0, strlen($prefix));
            if (strcasecmp($begin, $prefix) === 0) {
                $localePermissions[] = $permission->Code;
            }
        }
        return $localePermissions;
    }
}
