<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;

class LocalisedChild extends LocalisedParent implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_LocalisedChild';

    private static $translate = [
        'Record',
    ];

    private static $db = [
        'Record' => 'Text',
        'Data' => 'Text',
    ];
}
