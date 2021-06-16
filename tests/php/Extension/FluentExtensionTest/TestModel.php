<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class TestModel
 *
 * @property string $Title
 * @method TestRelationPage Parent()
 */
class TestModel extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'FluentExtensionTest_TestModel';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'Parent' => TestRelationPage::class . '.TestRelation',
    ];
}
