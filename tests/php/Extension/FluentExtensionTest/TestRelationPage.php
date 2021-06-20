<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use Page;
use SilverStripe\Dev\TestOnly;

/**
 * Class TestPage
 *
 * @property int $TestRelationID
 * @method TestModel TestRelation()
 */
class TestRelationPage extends Page implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'FluentExtensionTest_TestPage';

    /**
     * @var array
     */
    private static $has_one = [
        'TestRelation' => TestModel::class,
    ];

    private static array $field_include = [
        'TestRelationID',
    ];

    /**
     * @var array
     */
    private static $localised_copy = [
        'TestRelation',
    ];
}
