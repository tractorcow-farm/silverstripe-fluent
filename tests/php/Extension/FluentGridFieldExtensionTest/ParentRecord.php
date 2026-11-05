<?php

namespace TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Record whose edit form holds a nested GridField of localised children
 *
 * @property string $Title
 */
class ParentRecord extends DataObject implements TestOnly
{
    private static $table_name = 'FluentGridFieldExtensionTest_ParentRecord';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Children' => ChildRecord::class,
    ];
}
