<?php

namespace TractorCow\Fluent\Model\Delete;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Factory;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;

/**
 * Generate a delete policy for a class
 */
class DeletePolicyFactory implements Factory
{
    /**
     * Creates a new service instance.
     *
     * @param string $service The class name of the service.
     * @param array  $params  The constructor parameters.
     * @return DeletePolicy The created service instances.
     */
    public function create($service, array $params = [])
    {
        /** @var DataObject $object */
        $object = reset($params);
        if (!$object) {
            throw new InvalidArgumentException("Missing target argument required");
        }
        $dependantPolicies = [];

        // Fluent extension requires rows in current locale to be removed
        if ($object->hasExtension(FluentExtension::class)) {
            $dependantPolicies[] = new DeleteLocalisationPolicy();
        }

        // Filtered extension requires mapping table to locale to be removed
        if ($object->hasExtension(FluentFilteredExtension::class)) {
            $dependantPolicies[] = new DeleteFilterPolicy();
        }

        // Build policy
        $policy = new DeleteRecordPolicy();
        $policy->setDependantPolicies($dependantPolicies);
        return $policy;
    }
}
