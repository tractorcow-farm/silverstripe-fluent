<?php

namespace TractorCow\Fluent\Model\Delete;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLDelete;

/**
 * A policy that deletes the root record
 */
class DeleteRecordPolicy implements DeletePolicy
{
    /**
     * List of dependant policies
     *
     * @var DeletePolicy[]
     */
    protected $dependantPolicies = [];

    /**
     * @return DeletePolicy[]
     */
    public function getDependantPolicies()
    {
        return $this->dependantPolicies;
    }

    /**
     * @param DeletePolicy[] $dependantPolicies
     * @return $this
     */
    public function setDependantPolicies($dependantPolicies)
    {
        $this->dependantPolicies = $dependantPolicies;
        return $this;
    }

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
        $blocked = false;
        foreach ($this->getDependantPolicies() as $filter) {
            $blocked = $filter->delete($record) || $blocked;
        }

        // Blocked by upstream policy
        if ($blocked) {
            return true;
        }

        // Delete base record
        $this->deleteBaseRecord($record);
        return false;
    }

    /**
     * Do base record deletion
     *
     * @param DataObject $record
     */
    protected function deleteBaseRecord(DataObject $record)
    {
        // Copy DataObject::delete() here for now
        $srcQuery = DataList::create(get_class($record))
            ->filter('ID', $record->ID)
            ->dataQuery()
            ->query();
        $queriedTables = $srcQuery->queriedTables();
        foreach ($queriedTables as $table) {
            $delete = SQLDelete::create("\"{$table}\"", array('"ID"' => $record->ID));
            $delete->execute();
        }
    }
}
