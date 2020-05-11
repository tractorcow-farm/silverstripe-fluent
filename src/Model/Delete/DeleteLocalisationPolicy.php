<?php

namespace TractorCow\Fluent\Model\Delete;

use InvalidArgumentException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLSelect;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;

/**
 * A policy that deletes all localisations for a record
 *
 * Requires that the object has {@see FluentExtension}
 */
class DeleteLocalisationPolicy implements DeletePolicy
{
    use Injectable;

    /**
     * @param DataObject|FluentExtension $record
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
        if (!$record->hasExtension(FluentExtension::class)) {
            throw new InvalidArgumentException("This policy only works with localised objects (FluentExtension)");
        }

        // Delete localisations for each level of hierarchy
        $localisedTables = $record->getLocalisedTables();
        $classes = ClassInfo::ancestry($record, true);
        foreach ($classes as $class) {
            // Check main table name
            $table = DataObject::getSchema()->tableName($class);

            // Skip if table isn't localised
            if (!isset($localisedTables[$table])) {
                continue;
            }

            // Remove _Localised record
            $localisedTable = $record->deleteTableTarget($table, $locale);
            $localisedDelete = SQLDelete::create(
                "\"{$localisedTable}\"",
                [
                    '"Locale"'   => $locale->Locale,
                    '"RecordID"' => $record->ID,
                ]
            );
            $localisedDelete->execute();
        }

        // Check if this record has localisations for any other locale
        return $this->checkIfBlocked($record, $locale);
    }

    /**
     * Determine if this object has other localisations blocking deletion
     *
     * @param DataObject|FluentExtension $record
     * @param Locale                     $locale
     * @return bool
     */
    protected function checkIfBlocked(DataObject $record, Locale $locale)
    {
        $otherLocales = Locale::getCached()
            ->exclude('ID', $locale->ID)
            ->column('Locale');
        if (empty($otherLocales)) {
            // No locales; Nothing to block
            return false;
        }

        // Check if any localisations yet remain for this record (any locale)
        $localisedBaseTable = $record->deleteTableTarget($record->baseTable(), $locale);
        $localePlaceholders = DB::placeholders($otherLocales);
        $blockedQuery = SQLSelect::create()
            ->setSelect('COUNT ("ID")')
            ->setFrom("\"{$localisedBaseTable}\"")
            ->setWhere([
                "\"Locale\" IN ($localePlaceholders)" => $otherLocales,
                "\"RecordID\""                        => $record->ID,
            ]);

        // Any localisations exist for this record
        return $blockedQuery->execute()->value() > 0;
    }
}
