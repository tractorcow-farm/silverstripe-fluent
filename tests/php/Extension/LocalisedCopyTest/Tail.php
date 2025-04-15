<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property int RibbonID
 * @method Ribbon Ribbon()
 * @method Horse Parent()
 */
class Tail extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'Tail';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Ribbon' => Ribbon::class,
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'Parent' => Horse::class . '.Tail',
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Ribbon',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Ribbon',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Ribbon',
    ];
}
