<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Saddle
 *
 * @method Saddle Parent()
 */
class Saddle extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'Saddle';

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
        'Parent' => Steed::class . '.Saddle',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];
}
