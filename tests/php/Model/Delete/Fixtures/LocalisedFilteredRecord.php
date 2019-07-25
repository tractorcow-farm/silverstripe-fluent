<?php

namespace TractorCow\Fluent\Tests\Model\Delete\Fixtures;

use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;

class LocalisedFilteredRecord extends DataObject
{
    private static $table_name = 'FluentDeleteTest_LocalisedFilteredRecord';

    private static $extensions = [
        FluentExtension::class,
        FluentFilteredExtension::class,
    ];

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
