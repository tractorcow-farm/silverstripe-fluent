<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

class MixedLocalisedSortObject extends DataObject implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_MixedLocalisedSortObject';

    private static $translate = [
        'LocalizedSort',
        'Title',
    ];

    private static $extensions = [
        'FluentExtension' => FluentExtension::class,
    ];

    private static $field_exclude = [
        'NonLocalizedSort',
    ];

    private static $field_include = [
        'LocalizedSort',
    ];

    private static $data_include = [
        'Varchar',
    ];

    private static $db = [
        'Title' => 'Varchar',
        'NonLocalizedSort' => 'Int',
        'LocalizedSort' => 'Int',
    ];
}
