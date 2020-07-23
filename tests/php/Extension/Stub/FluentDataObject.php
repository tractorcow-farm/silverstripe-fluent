<?php

namespace TractorCow\Fluent\Tests\Extension\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class FluentDataObject
 *
 * @property string $Title
 * @property string $Description
 * @package TractorCow\Fluent\Tests\Extension\Stub
 */
class FluentDataObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'FluentTest_FluentDataObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
    ];
}
