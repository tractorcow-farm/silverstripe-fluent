<?php

namespace TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

/**
 * Localised, versioned record, edited either in its own list or nested in a parent
 *
 * @property string $Title
 * @property int $ParentID
 */
class ChildRecord extends DataObject implements TestOnly
{
    private static $table_name = 'FluentGridFieldExtensionTest_ChildRecord';

    private static $extensions = [
        'Versioned' => Versioned::class,
        'FluentExtension' => FluentVersionedExtension::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => ParentRecord::class,
    ];
}
