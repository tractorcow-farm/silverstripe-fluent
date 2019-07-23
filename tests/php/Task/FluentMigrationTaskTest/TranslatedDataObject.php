<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * Dataobject to test migration of old SS3 data to SS4
 *
 * Assuming we translated to de_AT and en_US
 *
 *
 * Class TranslatedDataObject
 * @package TractorCow\Fluent\Dev
 */
class TranslatedDataObject extends DataObject implements TestOnly
{
    private static $extensions = [
        FluentExtension::class,
        OldFluentDataExtension::class
    ];

    private static $db = [
        'Title' => 'Varchar',
        'Name' => 'Varchar'
    ];

    private static $translate = [
        'Title',
        'Name'
    ];

    private static $table_name = 'FluentTestDataObject';

    /**
     * @var array
     */
    private static $old_fluent_fields = [
        'Title_en_US',
        'Title_de_AT',
        'Name_en_US',
        'Name_de_AT'
    ];
}
