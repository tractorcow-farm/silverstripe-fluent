<?php

namespace TractorCow\Fluent\Tests\Extension\FluentExtensionTest;

use SilverStripe\Dev\TestOnly;

class LocalisedAnother extends LocalisedParent implements TestOnly
{
    private static $table_name = 'FluentExtensionTest_LocalisedAnother';

    private static $field_exclude = [
        'Record', // overrides data_include varchar
    ];

    private static $field_include = [
        'Data' // overrides data_exclude varchar(100)
    ];

    private static $db = [
        'Record' => 'Varchar',
        'Bastion' => 'Varchar',
        'Data' => 'Varchar(100)',
        'Cycle' => 'Varchar(100)',
    ];
}
