<?php

namespace TractorCow\Fluent\Tests\Extension\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A data object that gets FluentVersionedExtension before Versioned, which is not supported.
 * The test that uses it applies the extensions in that order.
 *
 * @property string $Title
 * @package TractorCow\Fluent\Tests\Extension\Stub
 */
class FluentMisorderedDataObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'FluentTest_FluentMisorderedDataObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
    ];
}
