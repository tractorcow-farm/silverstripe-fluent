<?php

namespace TractorCow\Fluent\Tests\Extension\LocalisedCopyTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class Animal
 *
 * @property string $Title
 */
class Animal extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'Animal';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
