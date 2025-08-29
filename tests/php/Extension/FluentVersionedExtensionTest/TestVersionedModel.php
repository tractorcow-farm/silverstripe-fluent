<?php

namespace TractorCow\Fluent\Tests\Extension\FluentVersionedExtensionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

/**
 * @mixin Versioned
 */
class TestVersionedModel extends DataObject implements TestOnly
{
    private static string $table_name = 'Fluent_TestVersionedModel';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        Versioned::class,
        FluentVersionedExtension::class,
    ];
}
