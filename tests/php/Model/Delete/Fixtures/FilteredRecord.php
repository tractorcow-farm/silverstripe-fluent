<?php

namespace TractorCow\Fluent\Tests\Model\Delete\Fixtures;

use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentFilteredExtension;

/**
 * @mixin FluentFilteredExtension
 */
class FilteredRecord extends DataObject
{
    private static $table_name = 'FluentDeleteTest_FilteredRecord';

    private static $extensions = [
        FluentFilteredExtension::class,
    ];

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
