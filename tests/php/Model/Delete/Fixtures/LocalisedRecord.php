<?php

namespace TractorCow\Fluent\Tests\Model\Delete\Fixtures;

use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * @mixin FluentExtension
 */
class LocalisedRecord extends DataObject
{
    private static $table_name = 'FluentDeleteTest_LocalisedRecord';

    private static $frontend_publish_required = false;

    private static $cms_publish_required = false;

    private static $extensions = [
        FluentExtension::class,
    ];

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
