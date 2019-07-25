<?php

namespace TractorCow\Fluent\Model\Delete;

use InvalidArgumentException;
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

    /**
     * @param DataObject|FluentFilteredExtension $record
     * @return bool
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
