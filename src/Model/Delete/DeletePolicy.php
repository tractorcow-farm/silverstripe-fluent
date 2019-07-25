<?php

namespace TractorCow\Fluent\Model\Delete;

use SilverStripe\ORM\DataObject;

interface DeletePolicy
{
    /**
     * @param DataObject $record
     * @return bool Determines if any dependent objects block upstream deletion (e.g. db / model constraints)
     */
    public function delete(DataObject $record);
}
