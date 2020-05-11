<?php

namespace TractorCow\Fluent\Model\Delete;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\State\FluentState;

/**
 * Archives a record, not just deletes
 */
class ArchiveRecordPolicy implements DeletePolicy
{
    use Injectable;

    /**
     * @param DataObject $record
     * @return bool Determines if any dependent objects block upstream deletion (e.g. db / model constraints)
     *              If this returns true, then there are additional conditions that must be satisfied before
     *              upstream relational constraints are safe to delete.
     *              If this returns true, then all downstream entities are reported purged, and upstream
     *              relational constraints can be deleted.
     */
    public function delete(DataObject $record)
    {
        if (!$record->hasExtension(Versioned::class)) {
            throw new InvalidArgumentException("This policy only works with versioned objects");
        }

        // Disable fluent, archive record
        /** @var DataObject|Versioned $record */
        return FluentState::singleton()
            ->withState(
                function (FluentState $state) use ($record) {
                    $state->setLocale(null);
                    $record->doArchive();
                }
            );
    }
}
