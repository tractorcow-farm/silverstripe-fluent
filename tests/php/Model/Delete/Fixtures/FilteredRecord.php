<?php

namespace TractorCow\Fluent\Tests\Model\Delete\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentFilteredExtension;

/**
 * @mixin FluentFilteredExtension
 */
class FilteredRecord extends DataObject implements TestOnly
{
    private static $table_name = 'FluentDeleteTest_FilteredRecord';

    private static $extensions = [
        FluentFilteredExtension::class,
    ];

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
