<?php

namespace TractorCow\Fluent\Extension;

use SilverStripe\Versioned\VersionableExtension;

/**
 * Extension for versioned localised objects
 */
class FluentVersionedExtension extends FluentExtension implements VersionableExtension
{
    /**
     * Mark this extension as versionable
     *
     * @config
     * @var array
     */
    private static $versionableExtensions = [
        self::class => [ self::SUFFIX ],
    ];

    /**
     * Determine if the given table is versionable
     *
     * @param string $table
     * @return bool True if versioned tables should be built for the given suffix
     */
    public function isVersionedTable($table)
    {
        // Build table if at least one localised field
        $class = get_class($this->owner);
        $localisedFields = $this->getLocalisedFields($class);
        return !empty($localisedFields);
    }

    /**
     * Update fields and indexes for the versionable suffix table
     *
     * @param string $suffix Table suffix being built
     * @param array $fields List of fields in this model
     * @param array $indexes List of indexes in this model
     */
    public function updateVersionableFields($suffix, &$fields, &$indexes)
    {
        $class = get_class($this->owner);
        $localisedFields = $this->getLocalisedFields($class);

        // Merge fields and indexes
        $fields = array_merge(
            $this->owner->config()->get('db_for_localised_table'),
            $localisedFields
        );
        $indexes = $this->owner->config()->get('indexes_for_localised_table');
    }

    public function extendWithSuffix($suffix)
    {
        return $suffix;
    }
}
