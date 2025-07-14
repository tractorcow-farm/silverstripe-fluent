<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

/**
 * @mixin FluentExtension
 */
class TestGeneratedColumns extends DataObject implements TestOnly
{
    private static string $table_name = 'FluentExtensionTest_TestGeneratedColumns';

    private static array $db = [
        'BaseField' => 'Varchar(255)',
        'GeneratedField1' => 'Generated("Varchar(255)", "CONCAT(\\"BaseField\\", \'_virtual\')", "VIRTUAL")',
        'GeneratedField2' => 'Generated("Varchar(255)", "CONCAT(\\"BaseField\\", \'_stored\')", "STORED")',
    ];

    private static array $extensions = [
        FluentExtension::class,
    ];

    private static $translate = [
        'BaseField',
        'GeneratedField1',
        'GeneratedField2',
    ];
}
