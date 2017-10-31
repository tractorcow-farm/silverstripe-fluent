<?php

namespace TractorCow\Fluent\Tests\Extension\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * @mixin FluentExtension
 */
class FluentStubObject extends DataObject implements TestOnly
{
    private static $extensions = [
        FluentExtension::class,
    ];
}
