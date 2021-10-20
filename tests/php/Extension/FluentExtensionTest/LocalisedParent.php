<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * @property string $Title
 * @property string $Description
 * @mixin FluentExtension
 */
class LocalisedParent extends DataObject implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_LocalisedParent';

    private static $translate = [
        'Details',
        'Title',
    ];

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
