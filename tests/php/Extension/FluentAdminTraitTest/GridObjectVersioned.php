<?php

namespace TractorCow\Fluent\Tests\Extension\FluentAdminTraitTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

/**
 * @mixin Versioned
 * @mixin FluentVersionedExtension
 */
class GridObjectVersioned extends DataObject implements TestOnly
{
    private static $table_name = 'FluentTest_GridObjectVersioned';

    private static $extensions = [
        'Versioned'       => Versioned::class,
        'FluentExtension' => FluentVersionedExtension::class,
    ];

    private static $db = [
        'Title'       => 'Varchar',
        'Description' => 'Varchar(100)',
    ];
}
