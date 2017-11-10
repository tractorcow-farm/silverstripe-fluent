<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * @mixin FluentExtension
 */
class LocalisedParent extends DataObject implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_LocalisedParent';

    private static $extensions = [
        'FluentExtension' => FluentExtension::class,
    ];

    private static $data_include = [
        'Varchar',
    ];

    private static $data_exclude = [
        'Varchar(100)',
    ];

    private static $db = [
        'Title' => 'Varchar',
        'Description' => 'Varchar(100)',
        'Details' => 'Varchar(200)',
    ];
}
