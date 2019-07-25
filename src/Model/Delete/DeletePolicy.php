<?php

namespace TractorCow\Fluent\Model\Delete;

use SilverStripe\ORM\DataObject;

interface DeletePolicy
{
    /**
     * @param DataObject $record
     * @return bool Determines if any dependent objects block upstream deletion (e.g. db / model constraints)
     *              If this returns true, then there are additional conditions that must be satisfied before
     *              upstream relational constraints are safe to delete.
     *              If this returns true, then all downstream entities are reported purged, and upstream
     *              relational constraints can be deleted.
     */
    public function delete(DataObject $record);
}
