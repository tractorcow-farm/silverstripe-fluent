<?php

namespace TractorCow\Fluent\Model\Delete;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Locale;

/**
 * A policy that deletes the filtered rows.
 *
 * Requires {@see FluentFilteredExtension} on the object
 */
class DeleteFilterPolicy implements DeletePolicy
{
    use Injectable;

    /**
     * @param DataObject|FluentFilteredExtension $record
     * @return bool Determines if any dependent objects block upstream deletion (e.g. db / model constraints)
     *              If this returns true, then there are additional conditions that must be satisfied before
     *              upstream relational constraints are safe to delete.
     *              If this returns true, then all downstream entities are reported purged, and upstream
     *              relational constraints can be deleted.
     */
    public function delete(DataObject $record)
    {
        // Ensure a locale exists
        $locale = Locale::getCurrentLocale();
        if (!$locale) {
            return false;
        }
        if (!$record->hasExtension(FluentFilteredExtension::class)) {
            throw new InvalidArgumentException("This policy only works with filtered objects (FluentFilteredExtension)");
        }

        // Remove filtered locale
        $record->FilteredLocales()->removeByID($locale->ID);

        // Check if this record is visible in any other locale
        return $record->FilteredLocales()->count() > 0;
    }
}
