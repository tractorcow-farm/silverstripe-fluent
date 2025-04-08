<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @method Tail Parent()
 */
class Ribbon extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'Ribbon';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'Parent' => Tail::class . '.Ribbon',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];
}
