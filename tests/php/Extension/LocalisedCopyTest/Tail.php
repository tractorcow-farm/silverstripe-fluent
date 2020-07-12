<?php

namespace TractorCow\Fluent\Tests\php\Extension\LocalisedCopyTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Tail
 *
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
    private static $belongs_to = [
        'Parent' => Horse::class . '.Tail',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];
}
