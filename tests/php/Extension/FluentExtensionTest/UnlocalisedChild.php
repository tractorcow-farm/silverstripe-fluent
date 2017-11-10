<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;

class UnlocalisedChild extends LocalisedParent implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_UnlocalisedChild';

    private static $translate = 'none';

    private static $db = [
        'Record' => 'Text',
        'Data' => 'Text',
    ];
}
